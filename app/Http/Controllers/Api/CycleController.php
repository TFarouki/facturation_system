<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DistributorCycle;
use App\Models\CycleMovement;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CycleController extends Controller
{
    public function index(Request $request)
    {
        $query = DistributorCycle::with(['distributor', 'movements.product']);
        
        if ($request->user()->role === 'distributor') {
            $query->where('distributor_id', $request->user()->id);
        }
        
        $cycles = $query->latest()->get();
        return response()->json($cycles);
    }

    public function store(Request $request)
    {
        $request->validate([
            'distributor_id' => 'required|exists:users,id',
            'start_date' => 'required|date',
        ]);

        $cycle = DistributorCycle::create([
            'distributor_id' => $request->distributor_id,
            'start_date' => $request->start_date,
            'status' => 'open',
        ]);

        return response()->json($cycle->load('distributor'), 201);
    }

    public function show(DistributorCycle $cycle)
    {
        return response()->json($cycle->load(['distributor', 'movements.product', 'salesReceipts.details']));
    }

    public function addMovement(Request $request, DistributorCycle $cycle)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|numeric|min:0.01',
            'movement_type' => 'required|in:load,reload,return',
        ]);

        if ($cycle->status === 'closed') {
            return response()->json(['error' => 'Cannot add movements to a closed cycle'], 400);
        }

        DB::beginTransaction();
        try {
            $movement = CycleMovement::create([
                'cycle_id' => $cycle->id,
                'product_id' => $request->product_id,
                'quantity' => $request->quantity,
                'movement_type' => $request->movement_type,
            ]);

            // Update stock based on movement type
            $product = Product::find($request->product_id);
            if ($request->movement_type === 'return') {
                $product->increment('current_stock_quantity', $request->quantity);
            } else {
                $product->decrement('current_stock_quantity', $request->quantity);
            }

            DB::commit();
            return response()->json($movement->load('product'), 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to add movement'], 500);
        }
    }

    public function close(Request $request, DistributorCycle $cycle)
    {
        if ($cycle->status === 'closed') {
            return response()->json(['error' => 'Cycle is already closed'], 400);
        }

        // Calculate totals for reconciliation
        $totalLoaded = $cycle->movements()
            ->whereIn('movement_type', ['load', 'reload'])
            ->sum('quantity');
            
        $totalReturned = $cycle->movements()
            ->where('movement_type', 'return')
            ->sum('quantity');
            
        $totalSold = $cycle->salesReceipts()
            ->join('sales_details', 'sales_receipts.id', '=', 'sales_details.receipt_id')
            ->sum('sales_details.quantity');

        $reconciliation = [
            'total_loaded' => $totalLoaded,
            'total_returned' => $totalReturned,
            'total_sold' => $totalSold,
            'expected_balance' => $totalLoaded - $totalReturned - $totalSold,
        ];

        $cycle->update([
            'status' => 'closed',
            'end_date' => now(),
        ]);

        return response()->json([
            'cycle' => $cycle->load(['distributor', 'movements', 'salesReceipts']),
            'reconciliation' => $reconciliation,
        ]);
    }
}
