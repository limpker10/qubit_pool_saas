<?php
// app/Models/Warehouse.php
namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Warehouse extends BaseModel
{
    use HasFactory;
    protected $fillable = ['name','code','address','is_active'];

    public function stocks() { return $this->hasMany(ProductStock::class); }
}
