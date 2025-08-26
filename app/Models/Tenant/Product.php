<?php
// app/Models/Product.php
namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends BaseModel
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name','sku','barcode','description','brand','category_id','unit_id',
        'default_cost_price','default_sale_price','min_stock','is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'default_cost_price' => 'decimal:4',
        'default_sale_price' => 'decimal:4',
    ];

    public function category() { return $this->belongsTo(Category::class); }
    public function unit() { return $this->belongsTo(Unit::class); }
    public function stocks() { return $this->hasMany(ProductStock::class); }
    public function kardex() { return $this->hasMany(KardexEntry::class); }
}
