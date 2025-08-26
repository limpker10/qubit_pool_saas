<?php
// app/Models/Category.php
namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends BaseModel
{
    use HasFactory;
    protected $fillable = ['name'];

    public function products() { return $this->hasMany(Product::class); }
}
