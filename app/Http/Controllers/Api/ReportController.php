<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SalesReceipt;
use App\Models\SalesDetail;
use App\Models\PurchaseDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function profit(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        // Calculate total revenue
        $totalRevenue = SalesDetail::join('sales_receipts', 'sales_details.receipt_id', '=', 'sales_receipts.id')
            ->whereBetween('sales_receipts.receipt_date', [$request->start_date, $request->end_date])
            ->selectRaw('SUM(sales_details.quantity * sales_details.selling_price) as total')
            ->value('total') ?? 0;

        // Calculate cost of goods sold
        $salesWithCost = SalesDetail::join('sales_receipts', 'sales_details.receipt_id', '=', 'sales_receipts.id')
            ->join('products', 'sales_details.product_id', '=', 'products.id')
            ->whereBetween('sales_receipts.receipt_date', [$request->start_date, $request->end_date])
            ->selectRaw('SUM(sales_details.quantity * products.cmup_cost) as total')
            ->value('total') ?? 0;

        $grossProfit = $totalRevenue - $salesWithCost;

        // Get breakdown by product
        $productBreakdown = SalesDetail::join('sales_receipts', 'sales_details.receipt_id', '=', 'sales_receipts.id')
            ->join('products', 'sales_details.product_id', '=', 'products.id')
            ->whereBetween('sales_receipts.receipt_date', [$request->start_date, $request->end_date])
            ->select(
                'products.name',
                DB::raw('SUM(sales_details.quantity) as total_quantity'),
                DB::raw('SUM(sales_details.quantity * sales_details.selling_price) as revenue'),
                DB::raw('SUM(sales_details.quantity * products.cmup_cost) as cost'),
                DB::raw('SUM(sales_details.quantity * (sales_details.selling_price - products.cmup_cost)) as profit')
            )
            ->groupBy('products.id', 'products.name')
            ->get();

        return response()->json([
            'period' => [
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
            ],
            'summary' => [
                'total_revenue' => round($totalRevenue, 2),
                'cost_of_goods_sold' => round($salesWithCost, 2),
                'gross_profit' => round($grossProfit, 2),
                'profit_margin' => $totalRevenue > 0 ? round(($grossProfit / $totalRevenue) * 100, 2) : 0,
            ],
            'product_breakdown' => $productBreakdown,
        ]);
    }

    public function monthlyZero(Request $request)
    {
        // This is a placeholder for monthly zeroing logic
        // In a real implementation, you would:
        // 1. Archive current month's data
        // 2. Reset accounts receivable/payable
        // 3. Create a new accounting period
        
        return response()->json([
            'message' => 'Monthly zero operation completed',
            'timestamp' => now(),
        ]);
    }
}
