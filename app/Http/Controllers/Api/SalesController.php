<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SalesReceipt;
use App\Models\SalesDetail;
use App\Models\DistributorCycle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class SalesController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'cycle_id' => 'required|exists:distributor_cycles,id',
            'distributor_id' => 'required|exists:users,id',
            'customer_name' => 'nullable|string|max:255',
            'receipt_date' => 'required|date',
            'receipt_image' => 'nullable|image|max:5120',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.selling_price' => 'required|numeric|min:0',
            'items.*.price_type_used' => 'required|in:wholesale,semi_wholesale,installment',
        ]);

        DB::beginTransaction();
        try {
            // Handle image upload
            $imagePath = null;
            if ($request->hasFile('receipt_image')) {
                $imagePath = $request->file('receipt_image')->store('receipts', 'public');
            }

            // Create receipt
            $receipt = SalesReceipt::create([
                'cycle_id' => $request->cycle_id,
                'distributor_id' => $request->distributor_id,
                'customer_name' => $request->customer_name,
                'receipt_date' => $request->receipt_date,
                'receipt_image_path' => $imagePath,
            ]);

            // Create receipt details
            foreach ($request->items as $item) {
                SalesDetail::create([
                    'receipt_id' => $receipt->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'selling_price' => $item['selling_price'],
                    'price_type_used' => $item['price_type_used'],
                ]);
            }

            DB::commit();
            return response()->json($receipt->load('details.product'), 201);
        } catch (\Exception $e) {
            DB::rollBack();
            if ($imagePath) {
                Storage::disk('public')->delete($imagePath);
            }
            return response()->json(['error' => 'Failed to create sales receipt'], 500);
        }
    }

    public function index(Request $request)
    {
        $query = SalesReceipt::with(['cycle', 'distributor', 'details.product']);
        
        if ($request->user()->role === 'distributor') {
            $query->where('distributor_id', $request->user()->id);
        }
        
        $receipts = $query->latest()->get();
        return response()->json($receipts);
    }

    public function show(SalesReceipt $receipt)
    {
        return response()->json($receipt->load(['cycle', 'distributor', 'details.product']));
    }
}
