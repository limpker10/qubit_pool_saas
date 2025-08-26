<?php
// app/Models/ProductStock.php
namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class ProductStock extends BaseModel
{
    use HasFactory;
    protected $fillable = ['product_id','warehouse_id','quantity','avg_unit_cost'];
    protected $casts = [ 'avg_unit_cost' => 'decimal:4' ];

    public function product() { return $this->belongsTo(Product::class); }
    public function warehouse() { return $this->belongsTo(Warehouse::class); }
}
