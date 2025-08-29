<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class PoolTable extends BaseModel
{
    use HasFactory;

    /** Nombre físico de la tabla (según tu migración) */
    protected $table = 'tables';

    /** Redondeo después de 1h (en minutos) */
    public const ROUND_BLOCK_MIN = 15;

    /* --------- Estados convenientes --------- */
    public const ST_AVAILABLE   = 'available';
    public const ST_IN_PROGRESS = 'in_progress';
    public const ST_PAUSED      = 'paused';
    public const ST_CANCELLED   = 'cancelled';

    protected $fillable = [
        'number', 'name',
        'type_id',                 // NUEVO: permitir asignación masiva
        'start_time', 'end_time',
        'amount', 'consumption',
        'status_id',
        'rate_per_hour', 'final_seconds', 'final_amount',
    ];


    protected $casts = [
        'type_id'      => 'integer',    // NUEVO
        'status_id'    => 'integer',
        'number'       => 'integer',
        'start_time'   => 'datetime',
        'end_time'     => 'datetime',
        'amount'       => 'decimal:2',
        'consumption'  => 'decimal:2',
        'final_amount' => 'decimal:2',
        'rate_per_hour'=> 'decimal:2',
        'final_seconds'=> 'integer',
    ];

    /** Para que salgan en el JSON automáticamente (opcional) */
    protected $appends = [
        'duration_seconds',
        'duration_human',
        'billable_minutes',
        'amount_now',
        'cover_url'
    ];

    /* --------- Relaciones --------- */

    public function getCoverUrlAttribute(): ?string
    {
        return $this->cover_path
            ? Storage::disk('public')->url($this->cover_path)
            : null;
    }

    public function status()
    {
        return $this->belongsTo(TableStatus::class, 'status_id');
    }

    public function type() // NUEVO
    {
        return $this->belongsTo(TableType::class, 'type_id');
    }

    /* --------- Scopes útiles --------- */

    public function scopeNumber($q, $number)
    {
        if ($number !== null && $number !== '') {
            $q->where('number', $number);
        }
        return $q;
    }

    public function scopeStatusName($q, ?string $name)
    {
        if ($name) {
            $q->whereHas('status', fn($s) => $s->where('name', $name));
        }
        return $q;
    }

    public function scopeTypeName($q, ?string $name) // NUEVO
    {
        if ($name) {
            $q->whereHas('type', fn($t) => $t->where('name', $name));
        }
        return $q;
    }

    /* --------- Estados helpers --------- */

    public function hasStatus(string $name): bool
    {
        $wantedId = TableStatus::idFor($name);
        if ($wantedId) {
            if ($this->status_id === $wantedId) return true;
        }
        return $this->status?->name === $name;
    }

    public function setStatusByName(string $name): void
    {
        $id = TableStatus::idFor($name);
        if ($id) $this->status_id = $id;
    }

    public function setTypeByName(string $name): void // NUEVO (por si lo necesitas)
    {
        $id = TableType::whereRaw('LOWER(name) = ?', [mb_strtolower(trim($name))])->value('id');
        if ($id) $this->type_id = $id;
    }

    /* --------- Cálculos de tiempo e importe --------- */

    public function computeElapsedSeconds(?Carbon $asOf = null): int
    {
        if (!$this->start_time) return 0;
        $asOf = $asOf ?: now();
        $end  = $this->end_time ?: $asOf;
        return $this->start_time->diffInSeconds($end);
    }

    public function computeBillableMinutes(?Carbon $asOf = null, int $block = self::ROUND_BLOCK_MIN): int
    {
        $secs = $this->computeElapsedSeconds($asOf);
        $mins = (int) ceil($secs / 60);

        if ($mins <= 15) return 15;
        if ($mins <= 30) return 30;
        if ($mins <= 60) return 60;

        $over  = $mins - 60;
        $block = max(1, $block);
        return 60 + (int) ceil($over / $block) * $block;
    }

    public function computeAmount(?Carbon $asOf = null, int $block = self::ROUND_BLOCK_MIN): float
    {
        $billableMins = $this->computeBillableMinutes($asOf, $block);
        $rate         = (float) ($this->rate_per_hour ?? 0);
        return round(($billableMins / 60) * $rate, 2);
    }

    /* --------- Atributos derivados --------- */

    public function getDurationSecondsAttribute(): ?int
    {
        $secs = $this->computeElapsedSeconds();
        return $secs > 0 ? $secs : null;
    }

    public function getDurationHumanAttribute(): ?string
    {
        $sec = $this->duration_seconds;
        if ($sec === null) return null;
        $h = intdiv($sec, 3600);
        $m = intdiv($sec % 3600, 60);
        $s = $sec % 60;
        return sprintf('%02d:%02d:%02d', $h, $m, $s);
    }

    public function getBillableMinutesAttribute(): ?int
    {
        if (!$this->start_time) return null;
        return $this->computeBillableMinutes();
    }

    public function getAmountNowAttribute(): ?string
    {
        if (!$this->start_time) return null;
        return number_format($this->computeAmount(), 2, '.', '');
    }
    public function rentals()
    {
        return $this->hasMany(TableRental::class, 'table_id');
    }

    /**
     * Alquiler abierto más reciente (si existe).
     * - Usa ofMany para elegir el de mayor started_at (y id como desempate).
     */
    public function activeRental(): HasOne
    {
        return $this->hasOne(TableRental::class, 'table_id')
            ->where('table_rentals.status', TableRental::ST_OPEN);
    }
}
