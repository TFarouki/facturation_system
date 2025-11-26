<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesDetail extends Model
{
    protected $fillable = ['receipt_id', 'product_id', 'quantity', 'selling_price', 'price_type_used'];
    
    protected $casts = [
        'quantity' => 'decimal:2',
        'selling_price' => 'decimal:2',
    ];

    public function receipt()
    {
        return $this->belongsTo(SalesReceipt::class, 'receipt_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
