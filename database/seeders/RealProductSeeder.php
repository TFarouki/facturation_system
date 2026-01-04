<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\Category;
use App\Models\Unit;

class RealProductSeeder extends Seeder
{
    public function run(): void
    {
        // Get the "قطعة" (Pcs) unit
        $unit = Unit::where('unit_name_ar', 'قطعة')->first();
        
        if (!$unit) {
            $this->command->warn('Unit "قطعة" not found. Please run UnitSeeder first!');
            return;
        }

        // Get or create a default category for these products
        $category = Category::firstOrCreate(
            ['name' => 'منتجات التنظيف'],
            ['name' => 'منتجات التنظيف']
        );

        // Products from the table image
        // Format: [name, gros (wholesale), demi_gros (semi-wholesale), detail (retail), stock_init]
        $products = [
            ['raclellette 45 cm', 7.30, null, 8.00, 924],
            ['raclette 55 cm', 8.30, null, 9.00, 586],
            ['confiture d\'bricot konouze 300g', 4.80, 5.00, 5.50, 3924],
            ['confiture d\'abricot seven 300g', 4.50, null, 5.00, 400],
            ['confiture de fraise seven 300g', 5.20, null, 4.70, null],
            ['confiture de fraise seven 720g', 10.50, null, 9.75, null],
            ['confiture d\'abricot pinova 760g', 17.50, null, 18.50, 18],
            ['confiture d\'abricot seven 800g', 11.00, null, 10.00, null],
            ['confiture d\'abricot konouze 700g', 10.00, 10.50, 11.00, 6512],
            ['siprop grenadine pinova 75cl', 8.40, null, 9.00, null],
            ['siprop menthe pinova 75cl', 8.40, null, 9.75, null],
            ['ketchup konoz 2kg', 16.50, 17.00, 18.00, 156],
            ['vinaigre konouze 50 cl', 62.00, 65.00, 67.00, 72],
            ['vinaigre konouz 20 cl', 27.00, 30.00, 32.00, null],
            ['confiture d\'abricot seven 3800g 5/1', 58.00, 60.00, 62.00, 444],
            ['alva 16', 4.00, 5.00, 6.00, null],
            ['alva 30', 9.00, 10.00, 11.00, 120],
            ['200 star(12)', 29.00, 30.00, 35.00, 42],
            ['alva (200t)', 36.00, null, 40.00, 60],
            ['alva (550)', 23.00, 24.00, 25.00, 222],
            ['alva (1200)', 38.00, null, 41.00, null],
            ['chiffon jaune', 4.00, 4.50, null, 200],
            ['rita brosse', 8.50, 9.00, 9.50, 1740],
            ['najmat chamal', 8.80, 9.00, 9.50, 906],
            ['brosse star', 9.50, 10.00, 10.50, 792],
            ['baton de fer 1,4', 6.50, 7.00, 7.50, 725],
            ['brosse mini', 3.20, null, 3.50, null],
            ['adizif (1,5kg)', 45, 48, 51, 486],
            ['bala rita *96', 3.2, 3.5, 4, 72],
            ['baton 1,20', 7.3, null, 7.5, 946],
            ['baton 1,40', 8.6, 9, 9.5, 600],
            ['chiffon alva', 4.8, 5, 5.5, 2143],
            ['adizif 1kg (45cm)', null, null, null, null],
            ['pisa 1500', 79, 80, 85, 99],
            ['pisa 350', 14.5, 15, 18, 444],
            ['pisa 600', 20, 22, 25, 348],
            ['pisa 800', 29, 30, 33, 246],
            ['alva 1500', null, null, null, null],
            ['abrasive 24', null, 8.5, null, 10],
            ['abrasive 28', null, 6.5, null, 9],
            ['sac poubelle 35 L / 40*20', null, 14, null, 15],
            ['sac poubelle 50 L / 20*35', null, null, null, null],
            ['miel kounoz 900*12', 11.5, 12.5, null, 15],
            ['miel konouz 450*24', 4.5, null, null, 6],
            ['spagehtti *20 500g', 5.4, 5.5, null, 6, 1860],
            ['scotch frajile blond', null, null, null, null],
            ['racellette 45cm24', null, 8.5, null, 9, 1392],
            ['racellette 55 cm24', null, 10, 10.5, 11, null],
            ['seven d abricot 350g', null, 8.8, null, 9, 10, 360],
            ['bala + brosse', null, 8.8, 9.5, null, 10, null],
            ['papier cuisson 16**12', null, 17.5, null, 18, 336],
            ['papier cuisson 11**24', null, 13, 14, 15, 432],
            ['spaghtti 250g*40', null, 3, null, 3.2, 320],
            ['tourchon petit**180', null, 5, 6, 7, 996],
            ['tourchon moyen**72 3p', null, 12, null, 14, 992],
            ['tourchon grand**48', null, 10, 11, 12, 264],
            ['sac 20*40 // 20p', null, 12.7, 13.5, 14, null],
            ['sac 40*20 // 40p', null, 6.8, null, 8, 1, 1535],
            ['sac 60*10 // 60p', null, 3.7, 3.8, 4, 960],
            ['chiffon coleur', null, 4, 4.5, 5, 350],
            ['شفون مارو', null, 3.5, null, 4, null],
            ['raclette55 smail', null, 14, null, 15, 216],
            ['raclette 45 smail', null, 13, null, 14, 276],
            ['scopa **15', null, 7, null, 7.5, null],
            ['raclette 45 goma 24', null, 7.5, null, 8, 96],
            ['raclette 55 goma 24', null, 8.5, null, 9, 1248],
        ];

        $codeCounter = 1;

        foreach ($products as $productData) {
            // Parse the data - handle variable array lengths
            $name = $productData[0];
            $wholesale = $productData[1] ?? 0;
            $semiWholesale = $productData[2] ?? null;
            $retail = $productData[3] ?? 0;
            $stock = isset($productData[4]) ? (is_numeric($productData[4]) ? $productData[4] : 0) : 0;
            
            // Skip if no prices at all
            if (!$wholesale && !$semiWholesale && !$retail) {
                continue;
            }

            // Calculate semi-wholesale if not provided (15% markup from wholesale)
            if (!$semiWholesale && $wholesale) {
                $semiWholesale = $wholesale * 1.10;
            }
            
            // If wholesale is null but semi-wholesale exists, estimate it
            if (!$wholesale && $semiWholesale) {
                $wholesale = $semiWholesale * 0.9;
            }

            // If retail is null, estimate from wholesale
            if (!$retail && $wholesale) {
                $retail = $wholesale * 1.2;
            }

            // Generate product code
            $productCode = 'PRD' . str_pad($codeCounter++, 4, '0', STR_PAD_LEFT);

            // Calculate buy price (cmup_cost) = wholesale * 0.7
            $cmupCost = $wholesale * 0.7;

            $product = Product::create([
                'name' => $name,
                'product_code' => $productCode,
                'category_id' => $category->id,
                'unit_id' => $unit->id,
                'current_stock_quantity' => $stock,
                'min_stock_level' => 10,
                'cmup_cost' => $cmupCost,
            ]);

            // Create price entry
            ProductPrice::create([
                'product_id' => $product->id,
                'wholesale_price' => $wholesale,
                'semi_wholesale_price' => $semiWholesale,
                'retail_price' => $retail,
                'tax_rate' => 0.00,
                'effective_date' => now(),
                'is_current' => true,
            ]);
        }

        $this->command->info('Created ' . ($codeCounter - 1) . ' products successfully!');
    }
}
