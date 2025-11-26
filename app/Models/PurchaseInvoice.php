<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseInvoice extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'supplier_id',
        'supplier_name',
        'invoice_number',
        'invoice_date',
        'invoice_image_path',
        'total_amount',
        'total_in_invoice',
        'it_has_def',
        'notes',
    ];
    
    protected $casts = [
        'invoice_date' => 'date',
        'total_amount' => 'decimal:2',
        'total_in_invoice' => 'decimal:2',
        'it_has_def' => 'boolean',
    ];

    public function details()
    {
        return $this->hasMany(PurchaseDetail::class, 'invoice_id');
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }
}
