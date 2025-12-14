<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DistributorPayment extends Model
{
    protected $fillable = [
        'distributor_id',
        'amount',
        'payment_date',
        'payment_method',
        'reference',
        'note',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payment_date' => 'date',
    ];

    public function distributor()
    {
        return $this->belongsTo(Distributor::class);
    }
}
