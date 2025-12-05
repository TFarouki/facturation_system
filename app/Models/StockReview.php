<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockReview extends Model
{
    protected $fillable = [
        'distributor_id',
        'review_date',
        'status',
        'notes',
        'confirmed_by',
        'confirmed_at',
    ];

    protected $casts = [
        'review_date' => 'date',
        'confirmed_at' => 'datetime',
    ];

    public function distributor()
    {
        return $this->belongsTo(Distributor::class);
    }

    public function details()
    {
        return $this->hasMany(StockReviewDetail::class);
    }

    public function confirmedBy()
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }
}

