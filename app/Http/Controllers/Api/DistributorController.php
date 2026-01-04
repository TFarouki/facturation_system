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
                    $total = 0;
                    foreach($sale->details as $detail) {
                        $price = $detail->selling_price ?? $detail->unit_price ?? 0;
                        $total += $detail->quantity * $price;
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

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'vehicle_plate' => 'nullable|string|max:50',
            'vehicle_type' => 'nullable|string|max:50',
            'notes' => 'nullable|string',
        ]);

        $distributor = Distributor::create($request->all());

        return response()->json($distributor, 201);
    }

    public function show(Distributor $distributor)
    {
        return response()->json($distributor);
    }

    public function update(Request $request, Distributor $distributor)
    {
        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'vehicle_plate' => 'nullable|string|max:50',
            'vehicle_type' => 'nullable|string|max:50',
            'notes' => 'nullable|string',
        ]);

        $distributor->update($request->all());

        return response()->json($distributor);
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
        $sales = $distributor->sales()->with(['details', 'payments'])->get();
        $hasUnpaidSales = false;
        foreach ($sales as $sale) {
             $total = 0;
             foreach($sale->details as $detail) {
                  $price = $detail->selling_price ?? $detail->unit_price ?? 0;
                  $total += $detail->quantity * $price;
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
                ->with(['client', 'details.product', 'payments'])
                ->get();
            
            $unpaidSales = $allSales->map(function ($sale) {
                $total = 0;
                foreach($sale->details as $detail) {
                    $price = $detail->selling_price ?? $detail->unit_price ?? 0;
                    $total += $detail->quantity * $price;
                }
                $paid = $sale->payments->sum('amount');
                $remaining = $total - $paid;
                
                // Calculate profit
                $profit = $sale->details->reduce(function ($carry, $item) {
                    $cost = ($item->quantity + ($item->promo_quantity ?? 0)) * ($item->product->cmup ?? 0);
                    $revenue = $item->quantity * $item->selling_price;
                    return $carry + ($revenue - $cost);
                }, 0);

                // Attach computed properties for frontend
                $sale->total_amount = $total;
                $sale->total_profit = $profit;
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

    public function addPayment(Request $request, $id)
    {
        $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'payment_date' => 'required|date',
            'payment_method' => 'required|string',
            'reference' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        $distributor = Distributor::findOrFail($id);
        
        $payment = $distributor->payments()->create([
            'amount' => $request->amount,
            'payment_date' => $request->payment_date,
            'payment_method' => $request->payment_method,
            'reference' => $request->reference,
            'notes' => $request->notes,
        ]);

        return response()->json($payment, 201);
    }

    public function getSettlementHistory($id)
    {
        $distributor = Distributor::findOrFail($id);
        $payments = $distributor->payments()
            ->orderBy('payment_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();
            
        return response()->json($payments);
    }
}
