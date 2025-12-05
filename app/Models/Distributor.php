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
        'notes',
    ];
}
