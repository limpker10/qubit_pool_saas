<?php

// app/Models/CashSession.php
namespace App\Models\Tenant;


class CashSession extends BaseModel
{
    protected $fillable = ['user_id', 'opened_at', 'closed_at', 'opening_cash', 'expected_cash', 'counted_cash', 'difference', 'notes', 'status'];
    protected $casts = ['opened_at' => 'datetime', 'closed_at' => 'datetime', 'opening_cash' => 'decimal:2', 'expected_cash' => 'decimal:2', 'counted_cash' => 'decimal:2', 'difference' => 'decimal:2'];

    public function movements()
    {
        return $this->hasMany(CashMovement::class);
    }

    public static function currentFor(int $userId): ?self
    {
        return static::where('user_id', $userId)->where('status', 'open')->latest('opened_at')->first();
    }
}
