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
                // Calculate balance: (Collected from clients) - (Paid to company)
                // Positive Balance = Distributor owes company (Has money in hand)
                // Negative Balance = Company owes distributor (Overpaid)
                $collected = $distributor->total_collected ?? 0;
                $settled = $distributor->total_settled ?? 0;
                $distributor->balance = $collected - $settled;
                return $distributor;
            });

        return response()->json($distributors);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:255',
            'vehicle_plate' => 'nullable|string|max:255',
            'vehicle_type' => 'nullable|string|max:255',
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
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:255',
            'vehicle_plate' => 'nullable|string|max:255',
            'vehicle_type' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);

        $distributor->update($request->all());
        return response()->json($distributor);
    }

    public function destroy(Distributor $distributor)
    {
        $distributor->delete();
        return response()->json(null, 204);
    }

    public function addPayment(Request $request, $id)
    {
        $distributor = Distributor::findOrFail($id);
        
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'payment_date' => 'required|date',
            'payment_method' => 'required|string',
            'reference' => 'nullable|string',
            'note' => 'nullable|string',
        ]);

        $payment = $distributor->payments()->create($validated);
        return response()->json($payment, 201);
    }

    public function getSettlementHistory($id)
    {
        $distributor = Distributor::findOrFail($id);
        
        $settlements = $distributor->payments()
            ->orderBy('payment_date', 'desc')
            ->get();

        // Calculate total stats
        $totalCollected = $distributor->salesPayments()->sum('amount');
        $totalSettled = $distributor->payments()->sum('amount');
        $balance = $totalCollected - $totalSettled;

        return response()->json([
            'settlements' => $settlements,
            'stats' => [
                'total_collected' => $totalCollected,
                'total_settled' => $totalSettled,
                'balance' => $balance
            ]
        ]);
    }

    public function getUnpaidSales($id)
    {
        try {
            $distributor = Distributor::findOrFail($id);
            
            // 1. Get Total Paid by Distributor to Company (Any method: Cash he gave us, or Check he wrote us to cover the cash)
            $totalSettled = $distributor->payments()->sum('amount');

            // 2. Get Sales where CASH was collected (Client -> Distributor)
            // Checks/Transfers are not "Carried Cash" liability.
            $sales = \App\Models\SalesReceipt::where('distributor_id', $id)
                ->with(['client', 'details.product', 'payments'])
                ->withSum(['payments as collected_cash' => function ($query) {
                    $query->where('payment_method', 'cash');
                }], 'amount')
                ->orderBy('receipt_date', 'asc') 
                ->orderBy('id', 'asc')
                ->get();
                
            // 3. Filter and Map: Deduct settled amount from collected CASH
            $liabilityReceipts = $sales->filter(function ($sale) {
                return ($sale->collected_cash ?? 0) > 0;
            })->map(function ($sale) use (&$totalSettled) {
                $collected_cash = (float) ($sale->collected_cash ?? 0);
                $settled = (float) $totalSettled;

                if ($settled >= $collected_cash) {
                    // Fully settled
                    $totalSettled = $settled - $collected_cash;
                    return null;
                } else {
                    // Part or None settled
                    $settledPart = $settled;
                    $remainingLiability = $collected_cash - $settledPart;
                    $totalSettled = 0; // Exhausted
                    
                    $sale->collected_amount = $collected_cash;
                    $sale->settled_part = $settledPart;
                    $sale->remaining_amount = $remainingLiability;
                    $sale->paid_amount = $collected_cash; // Show Cash Collected
                    
                    return $sale;
                }
            })->filter()
            ->values();

            $totalLiability = $liabilityReceipts->sum('remaining_amount');

            return response()->json([
                'sales' => $liabilityReceipts,
                'total_unpaid' => $totalLiability
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
