<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class TableType extends BaseModel
{
    use HasFactory;

    protected $table = 'table_types';

    protected $fillable = [
        'name',
        'description',
    ];

    protected $casts = [
        'name'        => 'string',
        'description' => 'string',
    ];

    /**
     * Relación con las mesas.
     * Ajusta el modelo si tu entidad de mesas se llama diferente.
     */
    public function tables()
    {
        // Si tu modelo de mesas se llama PoolTable y la FK es type_id:
        return $this->hasMany(\App\Models\PoolTable::class, 'type_id');
        // Alternativa si usas un modelo Table:
        // return $this->hasMany(\App\Models\Table::class, 'type_id');
    }
}
