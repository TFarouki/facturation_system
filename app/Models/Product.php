<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Category; // Added for the new relationship
use App\Models\ProductSellingPrice; // Added for existing relationship

use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'name',
        'product_code',
        'product_family',
        'product_family_id',
        'product_description',
        'unit_id',
        'current_stock_quantity',
        'cmup_cost',
        'barcode',
        'category_id',
    ];
    
    protected $casts = [
        'current_stock_quantity' => 'decimal:2',
        'cmup_cost' => 'decimal:2',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function productFamily()
    {
        return $this->belongsTo(ProductFamily::class);
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function prices()
    {
        return $this->hasMany(ProductPrice::class);
    }

    public function currentPrice()
    {
        return $this->hasOne(ProductPrice::class)->where('is_current', true);
    }
}
