<?php

namespace Database\Seeders;

use App\Models\Unit;
use Illuminate\Database\Seeder;

class UnitSeeder extends Seeder
{
    public function run(): void
    {
        $units = [
            ['unit_name_ar' => 'قطعة', 'unit_symbol_en' => 'Pcs', 'description' => 'وحدة قياس للقطع'],
            ['unit_name_ar' => 'كيلوغرام', 'unit_symbol_en' => 'Kg', 'description' => 'وحدة قياس للوزن'],
            ['unit_name_ar' => 'لتر', 'unit_symbol_en' => 'L', 'description' => 'وحدة قياس للسوائل'],
            ['unit_name_ar' => 'متر', 'unit_symbol_en' => 'M', 'description' => 'وحدة قياس للطول'],
            ['unit_name_ar' => 'علبة', 'unit_symbol_en' => 'Box', 'description' => 'وحدة قياس للعلب'],
            ['unit_name_ar' => 'كرتون', 'unit_symbol_en' => 'Carton', 'description' => 'وحدة قياس للكراتين'],
        ];

        foreach ($units as $unit) {
            Unit::create($unit);
        }
    }
}
