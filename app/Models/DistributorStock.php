<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DistributorStock extends Model
{
    protected $fillable = ['distributor_id', 'product_id', 'quantity'];

    public function distributor()
    {
        return $this->belongsTo(Distributor::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
