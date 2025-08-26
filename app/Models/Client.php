<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'document_type',        // DNI | RUC
        'document_number',
        'company',
        'plan_id',
        'is_active',
        'onboarded_at',
    ];

    protected $casts = [
        'is_active'    => 'boolean',
        'onboarded_at' => 'datetime',
    ];

    // ---------------------------
    // Relaciones
    // ---------------------------
    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    public function domains()
    {
        return $this->hasMany(ClientDomain::class);
    }

    // Dominio primario: ClientDomain con is_primary = true
    public function primaryDomain()
    {
        return $this->hasOne(ClientDomain::class)->where('is_primary', true);
    }

    public function contacts()
    {
        return $this->hasMany(ClientContact::class);
    }

    // Contacto principal
    public function primaryContact()
    {
        return $this->hasOne(ClientContact::class)->where('is_primary', true);
    }

    // Módulos habilitados
    public function modules()
    {
        return $this->belongsToMany(Module::class, 'client_module')
            ->withPivot(['enabled', 'settings', 'created_at', 'updated_at'])
            ->as('grant')           // Accede como $module->grant->enabled / ->settings
            ->withTimestamps();
    }

    // ---------------------------
    // Scopes / Accessors
    // ---------------------------
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // Documento formateado (ej. "RUC 2040..."/"DNI 8765...")
    public function getDocumentAttribute(): string
    {
        return "{$this->document_type} {$this->document_number}";
    }
}
