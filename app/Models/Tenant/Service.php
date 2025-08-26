<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;

class Service extends BaseModel {
    protected $fillable = ['code','name','description','billing_mode','unit','unit_price','is_active'];
}
