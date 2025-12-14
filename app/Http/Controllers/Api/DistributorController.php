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
                     }
                }
                $distributor->stock_value = $stockValue;

                // Calculate Unpaid Sales (Remaining Amount on Receipts)
                // We need to fetch all sales and calculate remaining manually since we don't have columns
                $sales = \App\Models\SalesReceipt::where('distributor_id', $distributor->id)
                    ->with(['details', 'payments'])
                    ->get();

                $unpaidSalesTotal = 0;
                foreach ($sales as $sale) {
                    $total = $sale->details->sum('subtotal'); // Assuming subtotal exists or calculate it: qty * price
                    if ($sale->details->isEmpty()) continue;
                    
                    // If subtotal is not on details, calculate it:
                    if ($total == 0) {
                        foreach($sale->details as $detail) {
                            $total += $detail->quantity * $detail->unit_price;
                        }
                    }
                    
                    $paid = $sale->payments->sum('amount');
                    $remaining = $total - $paid;
                    if ($remaining > 0.01) {
                         $unpaidSalesTotal += $remaining;
                    }
                }
                
                $distributor->balance = $stockValue + $unpaidSalesTotal;

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

        // Check 2: Financial Liability (Unpaid Sales)
        // Check manually
        $sales = $distributor->salesReceipts()->with(['details', 'payments'])->get();
        $hasUnpaidSales = false;
        foreach ($sales as $sale) {
             $total = 0;
             foreach($sale->details as $detail) {
                  $total += $detail->quantity * $detail->unit_price;
             }
             $paid = $sale->payments->sum('amount');
             if (($total - $paid) > 0.01) {
                 $hasUnpaidSales = true;
                 break;
             }
        }
        
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
            
            // 1. Stock Value
            $stockValue = 0;
            $stockItems = $distributor->stock()->with('product.currentPrice')->get();
            foreach($stockItems as $item) {
                 if (!empty($item->product->currentPrice)) {
                     $price = $item->product->currentPrice->wholesale_price; 
                     $stockValue += $item->quantity * $price;
                 }
            }

            // 2. Unpaid Sales
            $allSales = \App\Models\SalesReceipt::where('distributor_id', $id)
                ->with(['client', 'details', 'payments'])
                ->get();
            
            $unpaidSales = $allSales->map(function ($sale) {
                $total = 0;
                foreach($sale->details as $detail) {
                    $total += $detail->quantity * $detail->unit_price;
                }
                $paid = $sale->payments->sum('amount');
                $remaining = $total - $paid;
                
                // Attach computed properties for frontend
                $sale->total_amount = $total;
                $sale->paid_amount = $paid;
                $sale->remaining_amount = $remaining; // This is what frontend expects
                $sale->balance_due = $remaining;

                return $sale;
            })->filter(function ($sale) {
                return $sale->remaining_amount > 0.01;
            })->values();
            
            $totalUnpaidSales = $unpaidSales->sum('remaining_amount');
            $totalLiability = $stockValue + $totalUnpaidSales;

            return response()->json([
                'stock_value' => $stockValue,
                'total_unpaid_sales' => $totalUnpaidSales, 
                'total_liabilities' => $totalLiability,
                'sales' => $unpaidSales,
                'stock_items' => $stockItems
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
