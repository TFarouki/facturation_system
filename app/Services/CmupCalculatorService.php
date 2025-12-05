<?php

namespace App\Services;

use App\Models\Product;
use App\Models\PurchaseDetail;
use Illuminate\Support\Facades\DB;

class CmupCalculatorService
{
    /**
     * Calculate CMUP (Weighted Average Cost) for a product
     * 
     * Formula: CMUP = Total Cost / Total Quantity
     * Total Cost = Sum of (purchase_price * quantity) for all active purchase details
     * Total Quantity = Sum of all quantities in active purchase details
     * 
     * Note: Only considers active (non-deleted) purchase details
     * 
     * @param Product $product
     * @return float
     */
    public function calculateCmup(Product $product): float
    {
        // Get only active purchase details (excluding soft deleted ones)
        // Join with purchase_invoices to ensure invoice is not deleted
        $purchaseDetails = PurchaseDetail::join('purchase_invoices', 'purchase_details.invoice_id', '=', 'purchase_invoices.id')
            ->where('purchase_details.product_id', $product->id)
            ->whereNull('purchase_details.deleted_at')
            ->whereNull('purchase_invoices.deleted_at')
            ->select('purchase_details.quantity', 'purchase_details.purchase_price')
            ->get();

        if ($purchaseDetails->isEmpty()) {
            return 0.0;
        }

        $totalCost = 0;
        $totalQuantity = 0;

        foreach ($purchaseDetails as $detail) {
            $quantity = floatval($detail->quantity);
            $price = floatval($detail->purchase_price);
            
            $totalCost += ($quantity * $price);
            $totalQuantity += $quantity;
        }

        if ($totalQuantity == 0) {
            return 0.0;
        }

        return round($totalCost / $totalQuantity, 2);
    }

    /**
     * Recalculate CMUP for all products
     * Useful when you need to recalculate all CMUP values
     */
    public function recalculateAllCmup(): void
    {
        $products = Product::all();
        
        foreach ($products as $product) {
            $cmup = $this->calculateCmup($product);
            $product->update(['cmup_cost' => $cmup]);
        }
    }

    /**
     * Update CMUP for a product and save it
     * 
     * @param Product $product
     * @return float The calculated CMUP value
     */
    public function updateCmup(Product $product): float
    {
        $cmup = $this->calculateCmup($product);
        $product->update(['cmup_cost' => $cmup]);
        
        return $cmup;
    }

    /**
     * Update CMUP for multiple products
     * 
     * @param array $productIds
     */
    public function updateCmupForProducts(array $productIds): void
    {
        $products = Product::whereIn('id', $productIds)->get();
        
        foreach ($products as $product) {
            $this->updateCmup($product);
        }
    }

    /**
     * Update CMUP incrementally when adding a new purchase
     * More efficient than recalculating from all purchases
     * 
     * Formula: New CMUP = ((Old Stock × Old CMUP) + (New Price × New Quantity)) / (Old Stock + New Quantity)
     * 
     * @param Product $product The product to update
     * @param float $newQuantity The quantity being added
     * @param float $newPrice The purchase price of the new quantity
     * @return float The new CMUP value
     */
    public function updateCmupIncremental(Product $product, float $newQuantity, float $newPrice): float
    {
        // Get current stock before adding new purchase (refresh from database to ensure accuracy)
        $product->refresh();
        $oldStock = floatval($product->current_stock_quantity);
        $oldCmup = floatval($product->cmup_cost) ?: 0.0;

        // If no previous stock, CMUP is just the new price
        if ($oldStock <= 0) {
            $newCmup = floatval($newPrice);
        } else {
            // Incremental formula: ((Old Stock × Old CMUP) + (New Price × New Quantity)) / (Old Stock + New Quantity)
            $totalCost = ($oldStock * $oldCmup) + ($newPrice * $newQuantity);
            $totalQuantity = $oldStock + $newQuantity;
            $newCmup = $totalCost / $totalQuantity;
        }

        $newCmup = round($newCmup, 2);
        $product->update(['cmup_cost' => $newCmup]);
        
        return $newCmup;
    }

    /**
     * Reverse CMUP incrementally when removing a purchase
     * Used when updating or deleting invoices
     * 
     * Note: For accuracy, this recalculates from all purchases after removal
     * rather than using reverse formula which can be inaccurate
     * 
     * @param Product $product The product to update
     * @param float $removedQuantity The quantity being removed
     * @param float $removedPrice The purchase price of the removed quantity
     * @return float The new CMUP value after removal
     */
    public function reverseCmupIncremental(Product $product, float $removedQuantity, float $removedPrice): float
    {
        // For accuracy, we recalculate from all purchases after the removal
        // This is still efficient as we're only recalculating one product
        // and it ensures 100% accuracy
        return $this->updateCmup($product);
    }
}

