<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Distributor;
use App\Models\DistributorPayment;
use Illuminate\Http\Request;

class DistributorController extends Controller
{
    public function index()
    {
        $distributors = Distributor::withSum('salesPayments as total_collected', 'amount')
            ->withSum('payments as total_settled', 'amount')
            ->withCount(['stock as stock_items_count' => function ($query) {
                $query->where('quantity', '>', 0);
            }])
            ->get()
            ->map(function ($distributor) {
                // Determine Stock Value
                $stockValue = 0;
                $stockItems = $distributor->stock()->with('product.currentPrice')->get();
                foreach($stockItems as $item) {
                     if (!empty($item->product->currentPrice)) {
                         $price = $item->product->currentPrice->wholesale_price; 
                         $stockValue += $item->quantity * $price;
                     } else {
                         // Fallback using average price or cost if available, or just ignore
                     }
                }
                $distributor->stock_value = $stockValue;

                // Calculate balance: (Stock Value + Unpaid Sales)
                // 1. Unpaid Sales
                $totalSettled = $distributor->payments()->sum('amount');
                $sales = \App\Models\SalesReceipt::where('distributor_id', $distributor->id)
                    ->withSum(['payments as collected_cash' => function ($query) {
                        $query->where('payment_method', 'cash'); // Only Cash creates liability
                    }], 'amount')
                    ->where('payment_status', '!=', 'paid') // And check purely unpaid ones too
                    ->get();
                
                $collectedCashTotal = $sales->sum('collected_cash');
                // Also need to account for sales that are NOT paid at all
                $unpaidSalesTotal = \App\Models\SalesReceipt::where('distributor_id', $distributor->id)
                     ->where('payment_status', '!=', 'paid')
                     ->sum('remaining_amount');

                // This logic is getting complex. Let's simplify based on the request:
                // "Balance should be current liability: Value of all products in his car + Value of unpaid sales"
                // The previous logic was: Collection - Settlement.
                // New logic requested: Stock Value + Unpaid Sales Value.
                
                // Let's get Unpaid Sales Liability directly
                // Unpaid Sales Liability = Sales Total - Sales Paid (All methods) ?? 
                // Wait, "Unpaid Sales" usually means client hasn't paid.
                // But the context implies "Distributor Liability".
                // "Sales not paid" -> Sales where Client didn't pay?
                // Or Sales where Distributor collected money but didn't pay Company?
                // The Prompt says: "Value of all products in his car + Value of sales not paid"
                // "Sales not paid" typically means "Clients owe money".
                // If Client owes money, Distributor doesn't have it, so no liability?
                // UNLESS the model is: Distributor buys stock (liability), then sells it.
                // If Selling, stock goes down.
                // If he sells on credit, he has an Account Receivable.
                // If he has Stock, he has Asset.
                // His debt to company = Initial Stock Value taken - Payments made to company.
                
                // However, the prompt is specific: "Balance should be: Value of current products + Sales Unpaid".
                // Let's assume "Sales Unpaid" means "Sales made by distributor for which he hasn't settled with company yet" OR "Sales where client hasn't paid yet"?
                // "unpaid sales" in Arabic "المبيعات الغير مؤدات" usually means "Unpaid by Client".
                // If Client didn't pay, Distributor relies on Company to wait?
                // Let's stick to the prompt's specific formula:
                // Balance = Stock Value + Unpaid Sales (Remaining Amount on Receipts)

                // 2. Unpaid Sales (Remaining amount from Clients)
                $unpaidSales = \App\Models\SalesReceipt::where('distributor_id', $distributor->id)
                    ->sum('remaining_amount');
                
                $distributor->balance = $stockValue + $unpaidSales;

                return $distributor;
            });

        return response()->json($distributors);
    }
 
    public function destroy(Distributor $distributor)
    {
        // Check 1: Active Stock
        $hasStock = $distributor->stock()->where('quantity', '>', 0)->exists();
        if ($hasStock) {
             return response()->json(['message' => 'Cannot delete distributor with products in stock'], 409);
        }

        // Check 2: Financial Liability (Unpaid Sales or Settlements)
        // Check if there are any sales that are not fully paid
        $hasUnpaidSales = $distributor->salesReceipts()->where('payment_status', '!=', 'paid')->exists();
        if ($hasUnpaidSales) {
             return response()->json(['message' => 'Cannot delete distributor with unpaid sales'], 409);
        }

        $distributor->delete();
        return response()->json(null, 204);
    }

    public function getUnpaidSales($id)
    {
        try {
            $distributor = Distributor::findOrFail($id);
            
            // 1. Stock Value (Current Products in Car)
            $stockValue = 0;
            $stockItems = $distributor->stock()->with('product.currentPrice')->get();
            foreach($stockItems as $item) {
                 if (!empty($item->product->currentPrice)) {
                     $price = $item->product->currentPrice->wholesale_price; 
                     $stockValue += $item->quantity * $price;
                 }
            }

            // 2. Unpaid Sales (Sales NOT paid by clients)
            $unpaidSales = \App\Models\SalesReceipt::where('distributor_id', $id)
                ->where('balance_due', '>', 0) // Or remaining_amount
                ->with(['client', 'details'])
                ->get()
                ->map(function ($sale) {
                    $sale->remaining_amount = $sale->balance_due ?? $sale->total_amount - $sale->amount_paid;
                    return $sale;
                });
            
            $totalUnpaidSales = $unpaidSales->sum('remaining_amount');
            
            $totalLiability = $stockValue + $totalUnpaidSales;

            return response()->json([
                'stock_value' => $stockValue,
                'total_unpaid_sales' => $totalUnpaidSales, 
                'total_liabilities' => $totalLiability, // For the Red Card
                'sales' => $unpaidSales,
                'stock_items' => $stockItems // Optional if we want to show breakdown
            ]);

        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'Server Error',
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ], 500);
        }
    }

    public function getStocks($id)
    {
        $distributor = Distributor::findOrFail($id);
        $stocks = $distributor->stock()->with(['product.currentPrice', 'product.unit'])->get();
        return response()->json($stocks);
    }

    public function deletePayment($paymentId)
    {
        $payment = \App\Models\DistributorPayment::findOrFail($paymentId);
        $payment->delete();
        return response()->json(null, 204);
    }
}
