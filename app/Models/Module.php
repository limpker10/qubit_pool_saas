<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Module extends Model
{
    use HasFactory;

    protected $fillable = [
        'key', 'name', 'description', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // Clientes que tienen habilitado este módulo
    public function clients()
    {
        return $this->belongsToMany(Client::class, 'client_module')
            ->withPivot(['enabled', 'settings', 'created_at', 'updated_at'])
            ->as('grant')               // Accede como $module->grant->enabled
            ->withTimestamps();
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
