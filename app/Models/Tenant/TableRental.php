<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TableRental extends BaseModel
{
    use HasFactory;

    protected $table = 'table_rentals';

    // Estados
    public const ST_OPEN      = 'open';
    public const ST_CLOSED    = 'closed';
    public const ST_CANCELLED = 'cancelled';

    protected $fillable = [
        'table_id',
        'started_at',
        'ended_at',
        'elapsed_seconds',
        'rate_per_hour',
        'amount_time',
        'consumption',
        'discount',
        'surcharge',
        'total',
        'status',
        'opened_by_id',
        'closed_by_id',
        'meta',
    ];

    protected $casts = [
        'started_at'   => 'datetime',
        'ended_at'     => 'datetime',
        'meta'         => 'array',
        'elapsed_seconds' => 'integer',
        // Usa decimal:2 para que Eloquent devuelva string formateado con 2 decimales
        'rate_per_hour' => 'decimal:2',
        'amount_time'   => 'decimal:2',
        'consumption'   => 'decimal:2',
        'discount'      => 'decimal:2',
        'surcharge'     => 'decimal:2',
        'total'         => 'decimal:2',
    ];

    protected $appends = [
        'is_open',
        'duration_seconds',
        'duration_human',
    ];

    /* ================= Relaciones ================= */

    public function table(): BelongsTo
    {
        return $this->belongsTo(PoolTable::class, 'table_id');
    }

    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by_id');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by_id');
    }

    /* ================= Scopes ================= */

    public function scopeOpen($query)
    {
        return $query->where('status', self::ST_OPEN);
    }

    public function scopeClosed($query)
    {
        return $query->where('status', self::ST_CLOSED);
    }

    public function scopeCancelled($query)
    {
        return $query->where('status', self::ST_CANCELLED);
    }

    public function scopeStatus($query, ?string $status)
    {
        if ($status) {
            $query->where('status', $status);
        }
        return $query;
    }

    public function scopeForTable($query, $tableId)
    {
        if ($tableId) {
            $query->where('table_id', $tableId);
        }
        return $query;
    }

    /* ================= Atributos calculados ================= */

    public function getIsOpenAttribute(): bool
    {
        return $this->status === self::ST_OPEN;
    }

    public function getDurationSecondsAttribute(): ?int
    {
        // Si ya tenemos elapsed_seconds (cerrado), úsalo; si está abierto, calcula al vuelo
        if (!is_null($this->elapsed_seconds)) {
            return (int) $this->elapsed_seconds;
        }
        if ($this->started_at) {
            $end = $this->ended_at ?: now();
            return $this->started_at->diffInSeconds($end);
        }
        return null;
    }

    public function getDurationHumanAttribute(): ?string
    {
        $sec = $this->duration_seconds;
        if ($sec === null) return null;

        $h = intdiv($sec, 3600);
        $m = intdiv($sec % 3600, 60);
        $s = $sec % 60;

        return sprintf('%02dh %02dm %02ds', $h, $m, $s);
    }

    /* ================= Helpers de transición ================= */

    /**
     * Cierra el alquiler calculando montos. Puedes pasar overrides:
     * ['ended_at' => now(), 'rate_per_hour' => 12.5, 'consumption' => 10, 'discount' => 0, 'surcharge' => 0]
     */
    public function close(array $opts = []): self
    {
        $endedAt       = $opts['ended_at']     ?? now();
        $rate          = (float) ($opts['rate_per_hour'] ?? $this->rate_per_hour ?? 0);
        $consumption   = (float) ($opts['consumption']   ?? $this->consumption   ?? 0);
        $discount      = (float) ($opts['discount']      ?? 0);
        $surcharge     = (float) ($opts['surcharge']     ?? 0);

        $this->ended_at        = $endedAt;
        $this->elapsed_seconds = $this->started_at ? $this->started_at->diffInSeconds($endedAt) : 0;
        $this->rate_per_hour   = $rate;
        $this->amount_time     = round(($this->elapsed_seconds / 3600) * $rate, 2);
        $this->consumption     = $consumption;
        $this->discount        = $discount;
        $this->surcharge       = $surcharge;
        $this->total           = round($this->amount_time + $consumption - $discount + $surcharge, 2);
        $this->status          = self::ST_CLOSED;

        $this->save();

        return $this;
    }

    /**
     * Cancela el alquiler sin cobro. Puedes pasar un motivo para guardarlo en meta.
     */
    public function cancel(?string $reason = null): self
    {
        $this->ended_at     = $this->ended_at ?? now();
        $this->elapsed_seconds = $this->elapsed_seconds ?? ($this->started_at ? $this->started_at->diffInSeconds($this->ended_at) : 0);
        $this->amount_time  = 0;
        $this->consumption  = 0;
        $this->discount     = 0;
        $this->surcharge    = 0;
        $this->total        = 0;
        $this->status       = self::ST_CANCELLED;

        if ($reason) {
            $meta = $this->meta ?? [];
            $meta['cancel_reason'] = $reason;
            $this->meta = $meta;
        }

        $this->save();

        return $this;
    }
}
