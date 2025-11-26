<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\Category;
use App\Models\Unit;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $categories = Category::all();
        $units = Unit::all();

        if ($categories->isEmpty() || $units->isEmpty()) {
            $this->command->warn('Please run CategorySeeder and create units first!');
            return;
        }

        $products = [
            ['name' => 'Laptop HP ProBook', 'barcode' => 'LP001', 'wholesale' => 4500, 'retail' => 5500],
            ['name' => 'Samsung Galaxy Phone', 'barcode' => 'PH001', 'wholesale' => 3200, 'retail' => 4000],
            ['name' => 'Office Chair Ergonomic', 'barcode' => 'CH001', 'wholesale' => 850, 'retail' => 1200],
            ['name' => 'Desk Lamp LED', 'barcode' => 'LM001', 'wholesale' => 120, 'retail' => 180],
            ['name' => 'Notebook A4 Pack', 'barcode' => 'NB001', 'wholesale' => 25, 'retail' => 40],
            ['name' => 'Wireless Mouse', 'barcode' => 'MS001', 'wholesale' => 80, 'retail' => 120],
            ['name' => 'USB Flash Drive 32GB', 'barcode' => 'USB001', 'wholesale' => 45, 'retail' => 70],
            ['name' => 'HDMI Cable 2m', 'barcode' => 'CBL001', 'wholesale' => 35, 'retail' => 55],
            ['name' => 'Printer HP LaserJet', 'barcode' => 'PR001', 'wholesale' => 1800, 'retail' => 2400],
            ['name' => 'Monitor 24 inch', 'barcode' => 'MN001', 'wholesale' => 1200, 'retail' => 1600],
            ['name' => 'Keyboard Mechanical', 'barcode' => 'KB001', 'wholesale' => 250, 'retail' => 350],
            ['name' => 'Headphones Bluetooth', 'barcode' => 'HP001', 'wholesale' => 180, 'retail' => 280],
            ['name' => 'Power Bank 20000mAh', 'barcode' => 'PB001', 'wholesale' => 150, 'retail' => 220],
            ['name' => 'Phone Case Premium', 'barcode' => 'PC001', 'wholesale' => 40, 'retail' => 70],
            ['name' => 'Screen Protector', 'barcode' => 'SP001', 'wholesale' => 15, 'retail' => 30],
        ];

        foreach ($products as $productData) {
            $product = Product::create([
                'name' => $productData['name'],
                'barcode' => $productData['barcode'],
                'category_id' => $categories->random()->id,
                'unit_id' => $units->random()->id,
                'current_stock_quantity' => rand(10, 100),
            ]);

            // Create price
            ProductPrice::create([
                'product_id' => $product->id,
                'wholesale_price' => $productData['wholesale'],
                'semi_wholesale_price' => $productData['wholesale'] * 1.15,
                'retail_price' => $productData['retail'],
                'tax_rate' => [0, 7, 10, 20][rand(0, 3)],
                'effective_date' => now(),
                'is_current' => true,
            ]);
        }
    }
}
