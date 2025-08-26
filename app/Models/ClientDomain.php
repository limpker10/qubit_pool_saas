<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClientDomain extends Model
{
    use HasFactory;

    protected $table = 'client_domains';

    protected $fillable = [
        'client_id',
        'fqdn',         // acme.innovaservicios.pe
        'is_primary',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    // Scope útiles
    public function scopePrimary($query)
    {
        return $query->where('is_primary', true);
    }
}
