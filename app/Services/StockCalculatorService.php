<?php

namespace App\Services;

use App\Models\Product;
use App\Models\PurchaseDetail;
use App\Models\SalesDetail;
use App\Models\CycleMovement;
use Illuminate\Support\Facades\DB;

class StockCalculatorService
{
    /**
     * Calculate actual stock quantity for a product based on all transactions
     * 
     * Stock = Initial Stock (0) + Purchases - Sales - Cycle Loads/Reloads + Cycle Returns
     * 
     * @param Product $product
     * @return float
     */
    public function calculateStock(Product $product): float
    {
        $stock = 0.0;

        // Add all purchases (only active, non-deleted invoices)
        $totalPurchases = PurchaseDetail::join('purchase_invoices', 'purchase_details.invoice_id', '=', 'purchase_invoices.id')
            ->where('purchase_details.product_id', $product->id)
            ->whereNull('purchase_details.deleted_at')
            ->whereNull('purchase_invoices.deleted_at')
            ->sum('purchase_details.quantity');

        $stock += floatval($totalPurchases ?? 0);

        // Subtract all sales
        $totalSales = SalesDetail::join('sales_receipts', 'sales_details.receipt_id', '=', 'sales_receipts.id')
            ->where('sales_details.product_id', $product->id)
            ->sum('sales_details.quantity');

        $stock -= floatval($totalSales ?? 0);

        // Handle cycle movements
        // Load and reload subtract from stock, return adds to stock
        $totalCycleLoads = CycleMovement::where('product_id', $product->id)
            ->whereIn('movement_type', ['load', 'reload'])
            ->sum('quantity');

        $stock -= floatval($totalCycleLoads ?? 0);

        $totalCycleReturns = CycleMovement::where('product_id', $product->id)
            ->where('movement_type', 'return')
            ->sum('quantity');

        $stock += floatval($totalCycleReturns ?? 0);

        return max(0, round($stock, 2)); // Ensure non-negative
    }

    /**
     * Recalculate stock for all products
     */
    public function recalculateAllStock(): void
    {
        $products = Product::all();
        
        foreach ($products as $product) {
            $this->updateStock($product);
        }
    }

    /**
     * Update stock for a product and save it
     * 
     * @param Product $product
     * @return float The calculated stock value
     */
    public function updateStock(Product $product): float
    {
        $stock = $this->calculateStock($product);
        $product->update(['current_stock_quantity' => $stock]);
        
        return $stock;
    }

    /**
     * Update stock for multiple products
     * 
     * @param array $productIds
     */
    public function updateStockForProducts(array $productIds): void
    {
        $products = Product::whereIn('id', $productIds)->get();
        
        foreach ($products as $product) {
            $this->updateStock($product);
        }
    }
}

