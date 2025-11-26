<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TaxSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $taxes = [
            ['name' => 'Exempt', 'rate' => 0.00, 'description' => 'Tax Exempt'],
            ['name' => 'VAT 7%', 'rate' => 7.00, 'description' => 'Reduced VAT'],
            ['name' => 'VAT 10%', 'rate' => 10.00, 'description' => 'Standard VAT'],
            ['name' => 'VAT 14%', 'rate' => 14.00, 'description' => 'Intermediate VAT'],
            ['name' => 'VAT 20%', 'rate' => 20.00, 'description' => 'Standard VAT'],
        ];

        foreach ($taxes as $tax) {
            \App\Models\Tax::create($tax);
        }
    }
}
