<?php
// app/Models/Unit.php
namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Unit extends BaseModel
{
    use HasFactory;
    protected $fillable = ['name','abbreviation'];
    public function products() { return $this->hasMany(Product::class); }
}
