<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesReceipt extends Model
{
    protected $fillable = ['cycle_id', 'distributor_id', 'customer_name', 'receipt_date', 'receipt_image_path'];
    
    protected $casts = ['receipt_date' => 'date'];

    public function cycle()
    {
        return $this->belongsTo(DistributorCycle::class, 'cycle_id');
    }

    public function distributor()
    {
        return $this->belongsTo(User::class, 'distributor_id');
    }

    public function details()
    {
        return $this->hasMany(SalesDetail::class, 'receipt_id');
    }
}
