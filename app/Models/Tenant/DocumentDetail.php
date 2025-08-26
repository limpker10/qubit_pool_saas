<?php

namespace App\Models\Tenant;


class DocumentDetail extends BaseModel
{
    protected $table = 'documents_details'; // explícito por claridad
    protected $fillable = [
        'document_id', 'description', 'item_type', 'item_id',
        'quantity', 'unit', 'unit_price', 'line_total', 'tax', 'discount'
    ];

    public function document()
    {
        return $this->belongsTo(Document::class);
    }
}
