<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DistributionOrder;
use App\Models\DistributionOrderDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DistributionOrderController extends Controller
{
    public function index(Request $request)
    {
        $query = DistributionOrder::with(['distributor', 'details.product'])
            ->withCount('details')
            ->latest('order_date');

        // Filter by type if provided
        if ($request->has('order_type')) {
            $query->where('order_type', $request->order_type);
        }

        // Filter by distributor if provided
        if ($request->has('distributor_id')) {
            $query->where('distributor_id', $request->distributor_id);
        }

        return response()->json($query->get());
    }

    public function store(Request $request)
    {
        $request->validate([
            'distributor_id' => 'required|exists:distributors,id',
            'order_type' => 'required|in:sortie,entree',
            'order_number' => 'required|string|max:255|unique:distribution_orders',
            'order_date' => 'required|date',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
        ]);

        DB::beginTransaction();
        try {
            // Create order
            $order = DistributionOrder::create([
                'distributor_id' => $request->distributor_id,
                'order_type' => $request->order_type,
                'order_number' => $request->order_number,
                'order_date' => $request->order_date,
                'notes' => $request->notes,
            ]);

            // Create order details
            foreach ($request->items as $item) {
                DistributionOrderDetail::create([
                    'order_id' => $order->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                ]);
            }

            DB::commit();

            return response()->json([
                'order' => $order->load(['distributor', 'details.product']),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to create distribution order', 'message' => $e->getMessage()], 500);
        }
    }

    public function show(DistributionOrder $distributionOrder)
    {
        return response()->json($distributionOrder->load(['distributor', 'details.product']));
    }

    public function update(Request $request, DistributionOrder $distributionOrder)
    {
        $request->validate([
            'distributor_id' => 'required|exists:distributors,id',
            'order_type' => 'required|in:sortie,entree',
            'order_number' => 'required|string|max:255|unique:distribution_orders,order_number,' . $distributionOrder->id,
            'order_date' => 'required|date',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
        ]);

        DB::beginTransaction();
        try {
            // Update order
            $distributionOrder->update([
                'distributor_id' => $request->distributor_id,
                'order_type' => $request->order_type,
                'order_number' => $request->order_number,
                'order_date' => $request->order_date,
                'notes' => $request->notes,
            ]);

            // Delete old details
            $distributionOrder->details()->delete();

            // Create new details
            foreach ($request->items as $item) {
                DistributionOrderDetail::create([
                    'order_id' => $distributionOrder->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                ]);
            }

            DB::commit();

            return response()->json([
                'order' => $distributionOrder->load(['distributor', 'details.product']),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to update distribution order', 'message' => $e->getMessage()], 500);
        }
    }

    public function destroy(DistributionOrder $distributionOrder)
    {
        DB::beginTransaction();
        try {
            // Delete details first
            $distributionOrder->details()->delete();
            
            // Delete order
            $distributionOrder->delete();
            
            DB::commit();
            
            return response()->json(null, 204);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to delete distribution order', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Get next order number for current year
     * Format: YYYY + 5-digit sequential number (e.g., 202500001, 202500002)
     */
    public function getNextOrderNumber()
    {
        $currentYear = date('Y');
        
        // Get all order numbers for current year (excluding soft deleted)
        // Use withoutTrashed() to explicitly exclude soft-deleted records
        $orders = DistributionOrder::withoutTrashed()
            ->where('order_number', 'like', $currentYear . '%')
            ->pluck('order_number')
            ->toArray();
        
        $nextNumber = 1;
        
        // Find the highest sequential number
        foreach ($orders as $orderNumber) {
            // Check if order number matches pattern: YYYY + 5 digits
            if (preg_match('/^' . $currentYear . '(\d{5})$/', $orderNumber, $matches)) {
                $number = intval($matches[1]);
                if ($number >= $nextNumber) {
                    $nextNumber = $number + 1;
                }
            }
        }
        
        // Format: YYYY + 5-digit number (e.g., 202500001)
        $nextOrderNumber = $currentYear . str_pad($nextNumber, 5, '0', STR_PAD_LEFT);
        
        // Double-check that this number doesn't exist (including soft-deleted)
        // If it exists, increment and try again (max 10 attempts to avoid infinite loop)
        $attempts = 0;
        while (DistributionOrder::withTrashed()->where('order_number', $nextOrderNumber)->exists() && $attempts < 10) {
            $nextNumber++;
            $nextOrderNumber = $currentYear . str_pad($nextNumber, 5, '0', STR_PAD_LEFT);
            $attempts++;
        }
        
        return response()->json([
            'order_number' => $nextOrderNumber,
        ]);
    }

    /**
     * Get sales report (difference between sortie and entree)
     */
    public function salesReport(Request $request)
    {
        $request->validate([
            'distributor_id' => 'required|exists:distributors,id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
        ]);

        $distributorId = $request->distributor_id;
        $startDate = $request->start_date;
        $endDate = $request->end_date;

        // Get all sortie orders
        $sortieQuery = DistributionOrder::where('distributor_id', $distributorId)
            ->where('order_type', 'sortie');

        // Get all entree orders
        $entreeQuery = DistributionOrder::where('distributor_id', $distributorId)
            ->where('order_type', 'entree');

        if ($startDate) {
            $sortieQuery->where('order_date', '>=', $startDate);
            $entreeQuery->where('order_date', '>=', $startDate);
        }

        if ($endDate) {
            $sortieQuery->where('order_date', '<=', $endDate);
            $entreeQuery->where('order_date', '<=', $endDate);
        }

        $sortieOrders = $sortieQuery->with('details.product')->get();
        $entreeOrders = $entreeQuery->with('details.product')->get();

        // Calculate total quantities per product for sortie
        $sortieQuantities = [];
        foreach ($sortieOrders as $order) {
            foreach ($order->details as $detail) {
                $productId = $detail->product_id;
                if (!isset($sortieQuantities[$productId])) {
                    $sortieQuantities[$productId] = [
                        'product' => $detail->product,
                        'quantity' => 0,
                    ];
                }
                $sortieQuantities[$productId]['quantity'] += $detail->quantity;
            }
        }

        // Calculate total quantities per product for entree
        $entreeQuantities = [];
        foreach ($entreeOrders as $order) {
            foreach ($order->details as $detail) {
                $productId = $detail->product_id;
                if (!isset($entreeQuantities[$productId])) {
                    $entreeQuantities[$productId] = [
                        'product' => $detail->product,
                        'quantity' => 0,
                    ];
                }
                $entreeQuantities[$productId]['quantity'] += $detail->quantity;
            }
        }

        // Calculate sales (difference)
        $sales = [];
        $allProductIds = array_unique(array_merge(array_keys($sortieQuantities), array_keys($entreeQuantities)));

        foreach ($allProductIds as $productId) {
            $sortieQty = $sortieQuantities[$productId]['quantity'] ?? 0;
            $entreeQty = $entreeQuantities[$productId]['quantity'] ?? 0;
            $soldQty = $sortieQty - $entreeQty;

            if ($soldQty > 0) {
                $sales[] = [
                    'product' => $sortieQuantities[$productId]['product'] ?? $entreeQuantities[$productId]['product'],
                    'sortie_quantity' => $sortieQty,
                    'entree_quantity' => $entreeQty,
                    'sold_quantity' => $soldQty,
                ];
            }
        }

        return response()->json([
            'sales' => $sales,
            'total_products_sold' => count($sales),
        ]);
    }

    /**
     * Get committed products by distributor ID (route parameter)
     * Used in Distributor Stock Review page
     */
    public function getCommittedProductsByDistributor($distributorId)
    {
        return $this->fetchCommittedProducts($distributorId);
    }

    /**
     * Get committed products and quantities for a specific distributor
     * Used in Return Notes to show what products the distributor currently has
     */
    public function getCommittedProducts(Request $request)
    {
        $request->validate([
            'distributor_id' => 'required|exists:distributors,id',
        ]);

        return $this->fetchCommittedProducts($request->distributor_id);
    }

    /**
     * Fetch committed products for a distributor
     */
    private function fetchCommittedProducts($distributorId)
    {

        // Get all products that have been delivered to this distributor with first delivery date
        $deliveredProducts = DB::table('distribution_order_details')
            ->join('distribution_orders', 'distribution_order_details.order_id', '=', 'distribution_orders.id')
            ->where('distribution_orders.distributor_id', $distributorId)
            ->where('distribution_orders.order_type', 'sortie')
            ->whereNull('distribution_orders.deleted_at')
            ->select(
                'distribution_order_details.product_id', 
                DB::raw('SUM(distribution_order_details.quantity) as delivered_quantity'),
                DB::raw('MIN(distribution_orders.order_date) as first_delivery_date')
            )
            ->groupBy('distribution_order_details.product_id')
            ->get();

        // Get all products that have been returned by this distributor
        $returnedProducts = DB::table('distribution_order_details')
            ->join('distribution_orders', 'distribution_order_details.order_id', '=', 'distribution_orders.id')
            ->where('distribution_orders.distributor_id', $distributorId)
            ->where('distribution_orders.order_type', 'entree')
            ->whereNull('distribution_orders.deleted_at')
            ->select('distribution_order_details.product_id', DB::raw('SUM(distribution_order_details.quantity) as returned_quantity'))
            ->groupBy('distribution_order_details.product_id')
            ->get();

        // Get all products sold by this distributor
        // Note: Sales are linked to distributors through distributor_id in sales_receipts
        $soldProducts = DB::table('sales_details')
            ->join('sales_receipts', 'sales_details.receipt_id', '=', 'sales_receipts.id')
            ->where('sales_receipts.distributor_id', $distributorId)
            ->select('sales_details.product_id', DB::raw('SUM(sales_details.quantity) as sold_quantity'))
            ->groupBy('sales_details.product_id')
            ->get();

        // Convert to maps for easier lookup
        $deliveredMap = $deliveredProducts->pluck('delivered_quantity', 'product_id')->toArray();
        $firstDeliveryMap = $deliveredProducts->pluck('first_delivery_date', 'product_id')->toArray();
        $returnedMap = $returnedProducts->pluck('returned_quantity', 'product_id')->toArray();
        $soldMap = $soldProducts->pluck('sold_quantity', 'product_id')->toArray();

        // Get all product IDs that have committed quantities
        $productIds = array_unique(array_merge(
            array_keys($deliveredMap),
            array_keys($returnedMap),
            array_keys($soldMap)
        ));

        if (empty($productIds)) {
            return response()->json([]);
        }

        // Load products with relationships
        $products = \App\Models\Product::whereIn('id', $productIds)
            ->with(['category', 'unit', 'currentPrice'])
            ->get();

        // Calculate committed quantity for each product
        $committedProducts = [];
        foreach ($products as $product) {
            $deliveredQty = floatval($deliveredMap[$product->id] ?? 0);
            $returnedQty = floatval($returnedMap[$product->id] ?? 0);
            $soldQty = floatval($soldMap[$product->id] ?? 0);

            // Committed quantity = delivered - returned - sold
            $committedQty = max(0, $deliveredQty - $returnedQty - $soldQty);

            // Only include products with committed quantity > 0
            if ($committedQty > 0) {
                $committedProducts[] = [
                    'id' => $product->id,
                    'name' => $product->name,
                    'category' => $product->category,
                    'unit' => $product->unit,
                    'currentPrice' => $product->currentPrice,
                    'committed_quantity' => round($committedQty, 2),
                    'first_delivery_date' => $firstDeliveryMap[$product->id] ?? null,
                    'delivered_quantity' => round($deliveredQty, 2),
                    'returned_quantity' => round($returnedQty, 2),
                    'sold_quantity' => round($soldQty, 2),
                ];
            }
        }

        return response()->json($committedProducts);
    }
}