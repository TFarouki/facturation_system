<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CycleMovement extends Model
{
    protected $fillable = ['cycle_id', 'product_id', 'quantity', 'movement_type', 'movement_date'];
    
    protected $casts = [
        'quantity' => 'decimal:2',
        'movement_date' => 'datetime',
    ];

    public function cycle()
    {
        return $this->belongsTo(DistributorCycle::class, 'cycle_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
