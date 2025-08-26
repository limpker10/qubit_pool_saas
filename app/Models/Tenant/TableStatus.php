<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class TableStatus extends BaseModel
{
    use HasFactory;

    protected $table = 'table_statuses';

    protected $fillable = ['name'];

    public function tables()
    {
        return $this->hasMany(PoolTable::class, 'status_id');
    }

    /** Helper rápido para obtener el ID por nombre (in_progress, paused, etc.) */
    public static function idFor(string $name): ?int
    {
        return static::query()->where('name', $name)->value('id');
    }
}
