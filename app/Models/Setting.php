<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_name',
        'company_logo',
        'phone',
        'email',
        'address',
        'contact_person',
        'contact_phone',
        'semi_wholesale_percentage',
        'retail_percentage',
    ];

    protected $casts = [
        'semi_wholesale_percentage' => 'decimal:2',
        'retail_percentage' => 'decimal:2',
    ];

    // Helper to get the single settings instance
    public static function current()
    {
        return static::first() ?? static::create([
            'company_name' => 'My Company',
            'semi_wholesale_percentage' => 10.00,
            'retail_percentage' => 20.00,
        ]);
    }
}
