<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockReviewDetail extends Model
{
    protected $fillable = [
        'stock_review_id',
        'product_id',
        'committed_quantity',
        'actual_quantity',
        'difference',
    ];

    protected $casts = [
        'committed_quantity' => 'decimal:2',
        'actual_quantity' => 'decimal:2',
        'difference' => 'decimal:2',
    ];

    public function stockReview()
    {
        return $this->belongsTo(StockReview::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}

