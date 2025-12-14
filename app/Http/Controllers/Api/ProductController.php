<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\DistributionOrderDetail;
use App\Models\DistributionOrder;
use App\Models\SalesDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    public function index()
    {
        $products = Product::with(['currentPrice', 'category', 'unit', 'productFamily'])->get();
        
        // Get all product IDs
        $productIds = $products->pluck('id')->toArray();
        
        // Calculate delivery quantities (sortie) for all products in one query
        $deliveryQuantities = DB::table('distribution_order_details')
            ->join('distribution_orders', 'distribution_order_details.order_id', '=', 'distribution_orders.id')
            ->where('distribution_orders.order_type', 'sortie')
            ->whereNull('distribution_orders.deleted_at')
            ->whereIn('distribution_order_details.product_id', $productIds)
            ->select('distribution_order_details.product_id', DB::raw('SUM(distribution_order_details.quantity) as total'))
            ->groupBy('distribution_order_details.product_id')
            ->pluck('total', 'product_id')
            ->toArray();
        
        // Calculate return quantities (entree) for all products in one query
        $returnQuantities = DB::table('distribution_order_details')
            ->join('distribution_orders', 'distribution_order_details.order_id', '=', 'distribution_orders.id')
            ->where('distribution_orders.order_type', 'entree')
            ->whereNull('distribution_orders.deleted_at')
            ->whereIn('distribution_order_details.product_id', $productIds)
            ->select('distribution_order_details.product_id', DB::raw('SUM(distribution_order_details.quantity) as total'))
            ->groupBy('distribution_order_details.product_id')
            ->pluck('total', 'product_id')
            ->toArray();
        
        // Calculate sold quantities for all products in one query
        $soldQuantities = SalesDetail::whereIn('product_id', $productIds)
            ->select('product_id', DB::raw('SUM(quantity) as total'))
            ->groupBy('product_id')
            ->pluck('total', 'product_id')
            ->toArray();
        
        // Calculate committed quantity for each product
        foreach ($products as $product) {
            $deliveryQty = floatval($deliveryQuantities[$product->id] ?? 0);
            $returnQty = floatval($returnQuantities[$product->id] ?? 0);
            $soldQty = floatval($soldQuantities[$product->id] ?? 0);
            
            // Committed quantity = delivered - returned - sold
            $product->committed_quantity = max(0, $deliveryQty - $returnQty - $soldQty);
        }
        
        return response()->json($products);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'product_description' => 'nullable|string',
            'unit_id' => 'required|exists:units,id',
            'current_stock_quantity' => 'required|numeric|min:0',
            'wholesale_price' => 'required|numeric|min:0',
            'semi_wholesale_price' => 'required|numeric|min:0',
            'retail_price' => 'required|numeric|min:0',
            'tax_rate' => 'nullable|numeric|min:0|max:100',
            'barcode' => 'nullable|string|max:255',
            'category_id' => 'nullable|exists:categories,id',
            'product_code' => 'nullable|string|max:255',
            'product_family_id' => 'nullable|exists:product_families,id',
        ]);

        DB::beginTransaction();
        try {
            $product = Product::create([
                'name' => $request->name,
                'product_description' => $request->product_description,
                'unit_id' => $request->unit_id,
                'current_stock_quantity' => $request->current_stock_quantity,
                'barcode' => $request->barcode,
                'category_id' => $request->category_id,
                'product_code' => $request->product_code,
                'product_family_id' => $request->product_family_id,
                'cmup_cost' => $request->initial_cost ?? 0,
            ]);

            ProductPrice::create([
                'product_id' => $product->id,
                'wholesale_price' => $request->wholesale_price,
                'semi_wholesale_price' => $request->semi_wholesale_price,
                'retail_price' => $request->retail_price,
                'tax_rate' => $request->tax_rate ?? 0,
                'effective_date' => now(),
                'is_current' => true,
            ]);

            DB::commit();
            return response()->json($product->load('currentPrice', 'category', 'unit'), 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to create product: ' . $e->getMessage()], 500);
        }
    }

    public function show(Product $product)
    {
        return response()->json($product->load('currentPrice', 'category', 'unit', 'productFamily'));
    }

    public function update(Request $request, Product $product)
    {
        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'product_description' => 'nullable|string',
            'unit_id' => 'sometimes|required|exists:units,id',
            'current_stock_quantity' => 'sometimes|required|numeric|min:0',
            'barcode' => 'nullable|string|max:255',
            'category_id' => 'nullable|exists:categories,id',
            'product_code' => 'nullable|string|max:255',
            'product_family_id' => 'nullable|exists:product_families,id',
        ]);

        $product->update($request->only([
            'name',
            'product_description',
            'unit_id', 
            'current_stock_quantity',
            'current_stock_quantity',
            'barcode',
            'category_id',
            'product_code',
            'product_family_id'
        ]));
        
        return response()->json($product->load('currentPrice', 'category', 'unit'));
    }

    public function destroy(Product $product)
    {
        $product->delete();
        return response()->json(['message' => 'Product deleted successfully']);
    }

    public function updatePrices(Request $request, Product $product)
    {
        $request->validate([
            'wholesale_price' => 'required|numeric|min:0',
            'semi_wholesale_price' => 'required|numeric|min:0',
            'retail_price' => 'required|numeric|min:0',
            'tax_rate' => 'nullable|numeric|min:0|max:100',
            'effective_date' => 'nullable|date',
        ]);

        DB::beginTransaction();
        try {
            // Deactivate current price
            ProductPrice::where('product_id', $product->id)
                ->where('is_current', true)
                ->update(['is_current' => false]);

            // Create new price
            $newPrice = ProductPrice::create([
                'product_id' => $product->id,
                'wholesale_price' => $request->wholesale_price,
                'semi_wholesale_price' => $request->semi_wholesale_price,
                'retail_price' => $request->retail_price,
                'tax_rate' => $request->tax_rate ?? 0,
                'effective_date' => $request->effective_date ?? now(),
                'is_current' => true,
            ]);

            DB::commit();
            return response()->json($product->load('currentPrice', 'category', 'unit'));
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to update prices'], 500);
        }
    }

    public function stockHistory(Product $product)
    {
        try {
            $twelveMonthsAgo = now()->subMonths(12)->startOfMonth();
            
            // Get purchase details for this product in the last 12 months
            $purchases = DB::table('purchase_details')
                ->join('purchase_invoices', 'purchase_details.invoice_id', '=', 'purchase_invoices.id')
                ->where('purchase_details.product_id', $product->id)
                ->where('purchase_invoices.invoice_date', '>=', $twelveMonthsAgo)
                ->select(
                    DB::raw('DATE_FORMAT(purchase_invoices.invoice_date, "%Y-%m") as month'),
                    DB::raw('SUM(purchase_details.quantity) as total_quantity')
                )
                ->groupBy('month')
                ->orderBy('month')
                ->get();

            // Get sales details for this product in the last 12 months
            $sales = DB::table('sales_details')
                ->join('sales_receipts', 'sales_details.receipt_id', '=', 'sales_receipts.id')
                ->where('sales_details.product_id', $product->id)
                ->where('sales_receipts.receipt_date', '>=', $twelveMonthsAgo)
                ->select(
                    DB::raw('DATE_FORMAT(sales_receipts.receipt_date, "%Y-%m") as month'),
                    DB::raw('SUM(sales_details.quantity) as total_quantity')
                )
                ->groupBy('month')
                ->orderBy('month')
                ->get();

            // Get cycle movements for this product (load/reload subtract, return adds)
            $cycleMovements = DB::table('cycle_movements')
                ->where('cycle_movements.product_id', $product->id)
                ->where('cycle_movements.created_at', '>=', $twelveMonthsAgo)
                ->select(
                    DB::raw('DATE_FORMAT(cycle_movements.created_at, "%Y-%m") as month'),
                    DB::raw('SUM(CASE WHEN movement_type = "return" THEN quantity ELSE -quantity END) as net_quantity')
                )
                ->groupBy('month')
                ->orderBy('month')
                ->get();

            // Generate all months in the last 12 months
            $months = [];
            for ($i = 11; $i >= 0; $i--) {
                $months[] = now()->subMonths($i)->format('Y-m');
            }

            // Convert to maps for easier lookup
            $purchasesMap = $purchases->pluck('total_quantity', 'month')->toArray();
            $salesMap = $sales->pluck('total_quantity', 'month')->toArray();
            $cycleMap = $cycleMovements->pluck('net_quantity', 'month')->toArray();

            // Calculate stock level at the END of each month (forward calculation)
            // Start with stock 12 months ago (estimate: current stock minus all movements)
            $currentStock = floatval($product->current_stock_quantity);
            
            // Calculate total movements in the period
            $totalPurchases = array_sum(array_map('floatval', $purchasesMap));
            $totalSales = array_sum(array_map('floatval', $salesMap));
            $totalCycleNet = array_sum(array_map('floatval', $cycleMap));
            
            // Estimated starting stock 12 months ago
            $startingStock = max(0, $currentStock - $totalPurchases + $totalSales + $totalCycleNet);
            
            // Build response in chronological order with stock at END of each month
            $monthlyData = [];
            $runningStock = $startingStock;
            
            foreach ($months as $month) {
                $monthPurchases = floatval($purchasesMap[$month] ?? 0);
                $monthSales = floatval($salesMap[$month] ?? 0);
                $monthCycleNet = floatval($cycleMap[$month] ?? 0);
                
                // Stock at END of month = stock at start + purchases - sales - cycle movements
                // Cycle net is negative for load/reload, positive for return
                $runningStock = $runningStock + $monthPurchases - $monthSales - $monthCycleNet;
                $runningStock = max(0, $runningStock); // Ensure non-negative
                
                $monthlyData[] = [
                    'month' => $month,
                    'stock_level' => round($runningStock, 2), // Stock at END of month
                    'purchases' => round($monthPurchases, 2),
                    'sales' => round($monthSales, 2),
                    'cycle_net' => round($monthCycleNet, 2),
                ];
            }

            return response()->json([
                'product_id' => $product->id,
                'current_stock' => $currentStock,
                'monthly_data' => $monthlyData,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to calculate stock history',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function purchasePriceHistory(Product $product)
    {
        try {
            $twelveMonthsAgo = now()->subMonths(12)->startOfMonth();
            
            // Get average purchase price per month for this product
            $purchasePrices = DB::table('purchase_details')
                ->join('purchase_invoices', 'purchase_details.invoice_id', '=', 'purchase_invoices.id')
                ->where('purchase_details.product_id', $product->id)
                ->where('purchase_invoices.invoice_date', '>=', $twelveMonthsAgo)
                ->select(
                    DB::raw('DATE_FORMAT(purchase_invoices.invoice_date, "%Y-%m") as month'),
                    DB::raw('AVG(purchase_details.purchase_price) as avg_price'),
                    DB::raw('MIN(purchase_details.purchase_price) as min_price'),
                    DB::raw('MAX(purchase_details.purchase_price) as max_price')
                )
                ->groupBy('month')
                ->orderBy('month')
                ->get();

            // Generate all months in the last 12 months
            $months = [];
            for ($i = 11; $i >= 0; $i--) {
                $months[] = now()->subMonths($i)->format('Y-m');
            }

            // Convert to map for easier lookup
            $priceMap = [];
            foreach ($purchasePrices as $price) {
                $priceMap[$price->month] = [
                    'avg' => floatval($price->avg_price),
                    'min' => floatval($price->min_price),
                    'max' => floatval($price->max_price),
                ];
            }

            // Get latest purchase price as current
            $latestPurchase = DB::table('purchase_details')
                ->join('purchase_invoices', 'purchase_details.invoice_id', '=', 'purchase_invoices.id')
                ->where('purchase_details.product_id', $product->id)
                ->where('purchase_invoices.invoice_date', '>=', $twelveMonthsAgo)
                ->orderBy('purchase_invoices.invoice_date', 'desc')
                ->orderBy('purchase_details.created_at', 'desc')
                ->value('purchase_details.purchase_price');

            $currentPrice = $latestPurchase ? floatval($latestPurchase) : null;

            // Build monthly data
            $monthlyData = [];
            foreach ($months as $month) {
                $monthData = $priceMap[$month] ?? null;
                $monthlyData[] = [
                    'month' => $month,
                    'avg_price' => $monthData ? round($monthData['avg'], 2) : null,
                    'min_price' => $monthData ? round($monthData['min'], 2) : null,
                    'max_price' => $monthData ? round($monthData['max'], 2) : null,
                ];
            }

            return response()->json([
                'product_id' => $product->id,
                'current_price' => $currentPrice ? round($currentPrice, 2) : null,
                'monthly_data' => $monthlyData,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to calculate purchase price history',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
