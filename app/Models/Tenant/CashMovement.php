<?php

namespace App\Models\Tenant;


class CashMovement extends BaseModel
{
    protected $fillable = ['cash_session_id', 'type', 'amount', 'reference_type', 'reference_id', 'description'];
    protected $casts = ['amount' => 'decimal:2'];

    public function session()
    {
        return $this->belongsTo(CashSession::class, 'cash_session_id');
    }
}
