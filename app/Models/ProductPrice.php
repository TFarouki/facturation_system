<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductPrice extends Model
{
    protected $table = 'product_prices';
    
    protected $fillable = [
        'product_id',
        'wholesale_price',
        'semi_wholesale_price',
        'retail_price',
        'tax_rate',
        'effective_date',
        'is_current',
    ];

    protected $casts = [
        'wholesale_price' => 'decimal:2',
        'semi_wholesale_price' => 'decimal:2',
        'retail_price' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'effective_date' => 'date',
        'is_current' => 'boolean',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Scope to get only current prices
     */
    public function scopeCurrent($query)
    {
        return $query->where('is_current', true);
    }

    /**
     * Activate this price and deactivate others for the same product
     */
    public function activate()
    {
        // Deactivate all other prices for this product
        self::where('product_id', $this->product_id)
            ->where('id', '!=', $this->id)
            ->update(['is_current' => false]);

        // Activate this price
        $this->update(['is_current' => true]);
    }
}
