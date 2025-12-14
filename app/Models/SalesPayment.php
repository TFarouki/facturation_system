<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesPayment extends Model
{
    use \Illuminate\Database\Eloquent\SoftDeletes;

    protected $fillable = [
        'sales_receipt_id',
        'amount',
        'payment_date',
        'payment_method',
        'reference',
        'check_date',
        'note',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payment_date' => 'date',
        'check_date' => 'date',
    ];

    public function receipt()
    {
        return $this->belongsTo(SalesReceipt::class, 'sales_receipt_id');
    }
}
