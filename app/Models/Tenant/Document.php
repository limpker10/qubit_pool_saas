<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;

class Document extends BaseModel
{
    protected $fillable = ['type', 'series', 'number', 'issue_date', 'customer_id', 'currency', 'subtotal', 'tax', 'total', 'payment_method', 'status', 'cash_session_id', 'meta'];
    protected $casts = ['issue_date' => 'datetime', 'subtotal' => 'decimal:2', 'tax' => 'decimal:2', 'total' => 'decimal:2', 'meta' => 'array'];

    public function details()
    {
        return $this->hasMany(DocumentDetail::class);
    }

    public function cashSession()
    {
        return $this->belongsTo(CashSession::class);
    }
}
