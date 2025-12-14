<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesReceipt extends Model
{
    // Note: SoftDeletes will be enabled after migration runs
    use \Illuminate\Database\Eloquent\SoftDeletes;
    
    protected $fillable = ['receipt_number', 'distributor_id', 'client_id', 'receipt_date', 'receipt_image_path'];
    
    protected $casts = ['receipt_date' => 'date'];

    public function distributor()
    {
        return $this->belongsTo(Distributor::class, 'distributor_id');
    }

    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function details()
    {
        return $this->hasMany(SalesDetail::class, 'receipt_id');
    }

    public function payments()
    {
        return $this->hasMany(SalesPayment::class, 'sales_receipt_id');
    }
}
