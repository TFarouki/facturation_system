<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Category;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            'Electronics',
            'Clothing',
            'Food & Beverages',
            'Home & Garden',
            'Sports & Outdoors',
            'Books & Stationery',
            'Health & Beauty',
            'Toys & Games',
            'Automotive',
            'Office Supplies',
        ];

        foreach ($categories as $category) {
            Category::create(['name' => $category]);
        }
    }
}
