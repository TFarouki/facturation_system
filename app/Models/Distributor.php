<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Distributor extends Model
{
    use SoftDeletes;
    
    protected $fillable = [
        'name',
        'phone',
        'vehicle_plate',
        'vehicle_type',
    ];

    public function payments()
    {
        return $this->hasMany(DistributorPayment::class);
    }

    public function sales()
    {
        return $this->hasMany(SalesReceipt::class);
    }
    
    public function salesPayments()
    {
        return $this->hasManyThrough(SalesPayment::class, SalesReceipt::class, 'distributor_id', 'sales_receipt_id');
    }

    public function stock()
    {
        return $this->hasMany(DistributorStock::class);
    }

    public function transfers()
    {
        return $this->hasMany(StockTransfer::class);
    }
}
