<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DistributionOrder extends Model
{
    use SoftDeletes;
    
    protected $fillable = [
        'distributor_id',
        'order_type',
        'order_number',
        'order_date',
        'notes',
    ];
    
    protected $casts = [
        'order_date' => 'date',
    ];

    public function distributor()
    {
        return $this->belongsTo(Distributor::class);
    }

    public function details()
    {
        return $this->hasMany(DistributionOrderDetail::class, 'order_id');
    }
}
