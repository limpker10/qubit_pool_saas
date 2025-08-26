<?php
// app/Models/KardexEntry.php
namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class KardexEntry extends BaseModel
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'product_id','warehouse_id','movement','quantity_in','quantity_out',
        'unit_cost','total_cost','balance_qty','balance_avg_unit_cost','balance_total_cost',
        'document_type','document_id','movement_date','reference','description','created_by'
    ];

    protected $casts = [
        'movement_date' => 'datetime',
        'unit_cost' => 'decimal:4',
        'total_cost' => 'decimal:4',
        'balance_avg_unit_cost' => 'decimal:4',
        'balance_total_cost' => 'decimal:4',
    ];

    public function product() { return $this->belongsTo(Product::class); }
    public function warehouse() { return $this->belongsTo(Warehouse::class); }
    public function document() { return $this->morphTo(__FUNCTION__, 'document_type', 'document_id'); }
    public function user() { return $this->belongsTo(User::class, 'created_by'); }
}
