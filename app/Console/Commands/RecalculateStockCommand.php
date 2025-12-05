<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\StockCalculatorService;
use App\Services\CmupCalculatorService;

class RecalculateStockCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'stock:recalculate {--product-id= : Recalculate stock for a specific product ID} {--recalculate-cmup : Also recalculate CMUP after recalculating stock}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recalculate stock quantity for all products or a specific product based on purchases, sales, and cycle movements';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $stockCalculator = new StockCalculatorService();
        $cmupCalculator = new CmupCalculatorService();
        
        $productId = $this->option('product-id');
        $recalculateCmup = $this->option('recalculate-cmup');
        
        if ($productId) {
            $product = \App\Models\Product::find($productId);
            
            if (!$product) {
                $this->error("Product with ID {$productId} not found.");
                return 1;
            }
            
            $oldStock = $product->current_stock_quantity;
            
            $this->info("Recalculating stock for product: {$product->name} (ID: {$product->id})");
            $this->info("Old stock: {$oldStock}");
            
            $stock = $stockCalculator->updateStock($product);
            $this->info("New stock: {$stock}");
            
            if ($recalculateCmup) {
                $this->info("Recalculating CMUP...");
                $cmup = $cmupCalculator->updateCmup($product);
                $this->info("CMUP updated: {$cmup} DH");
            }
            
            return 0;
        }
        
        $this->info('Recalculating stock for all products...');
        
        $bar = $this->output->createProgressBar(\App\Models\Product::count());
        $bar->start();
        
        $stockCalculator->recalculateAllStock();
        
        $bar->finish();
        $this->newLine();
        $this->info('Stock recalculation completed for all products.');
        
        if ($recalculateCmup) {
            $this->info('Recalculating CMUP for all products...');
            $cmupCalculator->recalculateAllCmup();
            $this->info('CMUP recalculation completed.');
        }
        
        return 0;
    }
}
