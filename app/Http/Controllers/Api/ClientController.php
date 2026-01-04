<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\SalesReceipt;
use Illuminate\Http\Request;

class ClientController extends Controller
{
    /**
     * Display a listing of clients.
     */
    public function index(Request $request)
    {
        $query = Client::query();

        // Search by name or phone
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $clients = $query->orderBy('name')->get();

        // Calculate balance for each client
        $receipts = SalesReceipt::whereIn('client_id', $clients->pluck('id'))
            ->with(['details', 'payments'])
            ->get()
            ->groupBy('client_id');

        foreach ($clients as $client) {
            $clientReceipts = $receipts->get($client->id, collect());
            $totalSales = 0;
            $totalPaid = 0;
            
            foreach ($clientReceipts as $receipt) {
                foreach ($receipt->details as $detail) {
                    $totalSales += $detail->quantity * $detail->selling_price;
                }
                $totalPaid += $receipt->payments->sum('amount');
            }
            $client->balance = $totalSales - $totalPaid;
        }

        return response()->json($clients);
    }

    /**
     * Store a newly created client.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:500',
            'notes' => 'nullable|string',
        ]);

        $client = Client::create($validated);

        return response()->json($client, 201);
    }

    /**
     * Display the specified client.
     */
    public function show(Client $client)
    {
        return response()->json($client);
    }

    /**
     * Update the specified client.
     */
    public function update(Request $request, Client $client)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:500',
            'notes' => 'nullable|string',
        ]);

        $client->update($validated);

        return response()->json($client);
    }

    /**
     * Remove the specified client.
     */
    public function destroy(Client $client)
    {
        if ($client->salesReceipts()->exists()) {
            return response()->json(['message' => 'Cannot delete client with existing sales receipts'], 409);
        }

        $client->delete();

        return response()->json(['message' => 'Client deleted successfully']);
    }

    /**
     * Search clients by name (for autocomplete).
     */
    public function search(Request $request)
    {
        $search = $request->get('q', '');
        
        if (strlen($search) < 1) {
            return response()->json([]);
        }

        $clients = Client::where('name', 'like', "%{$search}%")
            ->orWhere('phone', 'like', "%{$search}%")
            ->orderBy('name')
            ->limit(10)
            ->get(['id', 'name', 'phone', 'address']);

        return response()->json($clients);
    }

    /**
     * Get unpaid sales for a specific client.
     */
    public function getUnpaidSales($id)
    {
        $unpaidSales = SalesReceipt::where('client_id', $id)
            ->with(['distributor', 'details', 'payments'])
            ->get()
            ->map(function ($receipt) {
                $total = 0;
                foreach ($receipt->details as $detail) {
                    $total += $detail->quantity * $detail->selling_price;
                }
                $paid = $receipt->payments->sum('amount');
                $receipt->total_amount = $total;
                $receipt->paid_amount = $paid;
                $receipt->remaining_amount = $total - $paid;
                return $receipt;
            })
            ->filter(function ($receipt) {
                return $receipt->remaining_amount > 0.01;
            })
            ->values();

        return response()->json($unpaidSales);
    }

    /**
     * Get statistics for a specific client.
     */
    public function getStatistics($id)
    {
        $client = Client::findOrFail($id);

        $receipts = SalesReceipt::where('client_id', $id)
            ->with(['details.product', 'payments'])
            ->get();

        $totalRevenue = 0;
        $totalCost = 0;
        $totalPaid = 0;
        $invoiceCount = $receipts->count();

        $productCounts = [];

        foreach ($receipts as $receipt) {
            $totalPaid += $receipt->payments->sum('amount');
            foreach ($receipt->details as $detail) {
                $revenue = $detail->quantity * $detail->selling_price;
                $cost = ($detail->quantity + ($detail->promo_quantity ?? 0)) * ($detail->product->cmup ?? 0);
                
                $totalRevenue += $revenue;
                $totalCost += $cost;

                $productId = $detail->product_id;
                if (!isset($productCounts[$productId])) {
                    $productCounts[$productId] = [
                        'name' => $detail->product->name ?? 'Unknown',
                        'quantity' => 0,
                        'total_spent' => 0
                    ];
                }
                $productCounts[$productId]['quantity'] += $detail->quantity;
                $productCounts[$productId]['total_spent'] += $revenue;
            }
        }

        // Top 5 products
        uasort($productCounts, function($a, $b) {
            return $b['quantity'] <=> $a['quantity'];
        });
        $topProducts = array_slice($productCounts, 0, 5, true);

        // Monthly Trend (Last 6 months)
        $months = [];
        for ($i = 5; $i >= 0; $i--) {
            $month = now()->subMonths($i)->format('Y-m');
            $months[$month] = 0;
        }

        foreach ($receipts as $receipt) {
            $month = date('Y-m', strtotime($receipt->receipt_date));
            if (isset($months[$month])) {
                $receiptTotal = 0;
                foreach($receipt->details as $d) {
                    $receiptTotal += $d->quantity * $d->selling_price;
                }
                $months[$month] += $receiptTotal;
            }
        }

        $trendPoints = [];
        foreach ($months as $month => $total) {
            $trendPoints[] = [
                'month' => $month,
                'total' => $total
            ];
        }

        return response()->json([
            'client_name' => $client->name,
            'total_revenue' => $totalRevenue,
            'total_paid' => $totalPaid,
            'total_remaining' => $totalRevenue - $totalPaid,
            'total_profit' => $totalRevenue - $totalCost,
            'invoice_count' => $invoiceCount,
            'top_products' => array_values($topProducts),
            'monthly_trend' => $trendPoints
        ]);
    }
}
