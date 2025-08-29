<?php
// app/Models/Tenant/TableRentalItem.php
namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\SoftDeletes;

class TableRentalItem extends BaseModel
{
    use SoftDeletes;

    protected $fillable = [
        'table_rental_id','product_id','product_name','unit_id','unit_name',
        'qty','unit_price','discount','total','status','observation','client_op_id','created_by_id'
    ];

    protected $casts = [
        'qty'         => 'decimal:4',
        'unit_price'  => 'decimal:4',
        'discount'    => 'decimal:2',
        'total'       => 'decimal:2',
    ];

    public function rental()  { return $this->belongsTo(TableRental::class, 'table_rental_id'); }
    public function product() { return $this->belongsTo(Product::class); }
    public function unit()    { return $this->belongsTo(Unit::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by_id'); }

    // Calcula total si no viene (seguridad)
    protected static function booted()
    {
        static::saving(function (self $m) {
            $qty  = (float) $m->qty;
            $up   = (float) $m->unit_price;
            $desc = (float) $m->discount;
            if (!$m->total || $m->total <= 0) {
                $m->total = round($qty * $up - $desc, 2);
            }
            if (!$m->product_name && $m->product) {
                $m->product_name = $m->product->name;
            }
            if (!$m->unit_name && $m->unit) {
                $m->unit_name = $m->unit->name ?? null;
            }
        });
    }
}
