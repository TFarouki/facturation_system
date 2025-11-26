<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DistributorCycle extends Model
{
    protected $fillable = ['distributor_id', 'start_date', 'end_date', 'status'];
    
    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function distributor()
    {
        return $this->belongsTo(User::class, 'distributor_id');
    }

    public function movements()
    {
        return $this->hasMany(CycleMovement::class, 'cycle_id');
    }

    public function salesReceipts()
    {
        return $this->hasMany(SalesReceipt::class, 'cycle_id');
    }
}
