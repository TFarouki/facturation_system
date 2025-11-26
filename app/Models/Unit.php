<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\SoftDeletes;

class Unit extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'unit_name_ar',
        'unit_name_en',
        'unit_symbol_en',
        'description',
    ];

    public function products()
    {
        return $this->hasMany(Product::class);
    }
}
