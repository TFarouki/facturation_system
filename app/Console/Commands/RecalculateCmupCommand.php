<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\CmupCalculatorService;
use App\Services\StockCalculatorService;

class RecalculateCmupCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cmup:recalculate {--product-id= : Recalculate CMUP for a specific product ID} {--recalculate-stock : Also recalculate stock before recalculating CMUP}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recalculate CMUP (Weighted Average Cost) for all products or a specific product';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $cmupCalculator = new CmupCalculatorService();
        $stockCalculator = new StockCalculatorService();
        
        $productId = $this->option('product-id');
        $recalculateStock = $this->option('recalculate-stock');
        
        if ($productId) {
            $product = \App\Models\Product::find($productId);
            
            if (!$product) {
                $this->error("Product with ID {$productId} not found.");
                return 1;
            }
            
            if ($recalculateStock) {
                $this->info("Recalculating stock first...");
                $stock = $stockCalculator->updateStock($product);
                $this->info("Stock updated: {$stock}");
            }
            
            $this->info("Recalculating CMUP for product: {$product->name} (ID: {$product->id})");
            $cmup = $cmupCalculator->updateCmup($product);
            $this->info("CMUP updated: {$cmup} DH");
            
            return 0;
        }
        
        if ($recalculateStock) {
            $this->info('Recalculating stock for all products first...');
            $stockCalculator->recalculateAllStock();
            $this->info('Stock recalculation completed.');
        }
        
        $this->info('Recalculating CMUP for all products...');
        
        $cmupCalculator->recalculateAllCmup();
        
        $this->info('CMUP recalculation completed for all products.');
        
        return 0;
    }
}
