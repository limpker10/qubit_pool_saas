<?php
// app/Models/Tenant/BaseModel.php
namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;

abstract class BaseModel extends Model
{
    // Fuerza siempre la conexión del tenant
    protected $connection = 'tenant';

    protected $guarded = []; // o define $fillable en cada modelo
    public $timestamps = true;
}
