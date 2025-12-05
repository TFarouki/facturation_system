<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SalesReceipt;
use App\Models\SalesDetail;
use App\Models\Distributor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class SalesController extends Controller
{
    public function store(Request $request)
    {
        // Decode items if sent as JSON string (when using FormData with file upload)
        if (is_string($request->items)) {
            $request->merge(['items' => json_decode($request->items, true)]);
        }

        $request->validate([
            'receipt_number' => 'required|string|max:255|unique:sales_receipts',
            'distributor_id' => 'required|exists:distributors,id',
            'client_id' => 'required|exists:clients,id',
            'receipt_date' => 'required|date',
            'receipt_image' => 'nullable|mimes:jpeg,jpg,png,pdf|max:5120',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.selling_price' => 'required|numeric|min:0',
            'items.*.price_type_used' => 'required|in:wholesale,semi_wholesale,retail',
        ]);

        DB::beginTransaction();
        try {
            // Handle file upload (image or PDF)
            $filePath = null;
            if ($request->hasFile('receipt_image')) {
                $file = $request->file('receipt_image');
                $extension = $file->getClientOriginalExtension();
                $fileName = 'receipt_' . time() . '.' . $extension;
                $filePath = $file->storeAs('receipts', $fileName, 'public');
            }

            // Create receipt
            $receipt = SalesReceipt::create([
                'receipt_number' => $request->receipt_number,
                'distributor_id' => $request->distributor_id,
                'client_id' => $request->client_id,
                'receipt_date' => $request->receipt_date,
                'receipt_image_path' => $filePath,
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
            if ($filePath) {
                Storage::disk('public')->delete($filePath);
            }
            return response()->json(['error' => 'Failed to create sales receipt', 'message' => $e->getMessage()], 500);
        }
    }

    public function index(Request $request)
    {
        $query = SalesReceipt::with(['distributor', 'client', 'details.product']);
        
        // Filter by distributor if provided
        if ($request->has('distributor_id')) {
            $query->where('distributor_id', $request->distributor_id);
        }
        
        $receipts = $query->latest('receipt_date')->get();
        return response()->json($receipts);
    }

    public function show(SalesReceipt $receipt)
    {
        return response()->json($receipt->load(['distributor', 'client', 'details.product']));
    }

    public function update(Request $request, SalesReceipt $receipt)
    {
        // Decode items if sent as JSON string (when using FormData with file upload)
        if (is_string($request->items)) {
            $request->merge(['items' => json_decode($request->items, true)]);
        }

        $request->validate([
            'receipt_number' => 'required|string|max:255|unique:sales_receipts,receipt_number,' . $receipt->id,
            'distributor_id' => 'required|exists:distributors,id',
            'client_id' => 'required|exists:clients,id',
            'receipt_date' => 'required|date',
            'receipt_image' => 'nullable|mimes:jpeg,jpg,png,pdf|max:5120',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.selling_price' => 'required|numeric|min:0',
            'items.*.price_type_used' => 'required|in:wholesale,semi_wholesale,retail',
        ]);

        DB::beginTransaction();
        try {
            // Handle file upload (image or PDF)
            $filePath = $receipt->receipt_image_path;
            if ($request->hasFile('receipt_image')) {
                // Delete old file if exists
                if ($filePath) {
                    Storage::disk('public')->delete($filePath);
                }
                
                $file = $request->file('receipt_image');
                $extension = $file->getClientOriginalExtension();
                $fileName = 'receipt_' . time() . '.' . $extension;
                $filePath = $file->storeAs('receipts', $fileName, 'public');
            }

            // Update receipt
            $receipt->update([
                'receipt_number' => $request->receipt_number,
                'distributor_id' => $request->distributor_id,
                'client_id' => $request->client_id,
                'receipt_date' => $request->receipt_date,
                'receipt_image_path' => $filePath,
            ]);

            // Delete old details
            $receipt->details()->delete();

            // Create new receipt details
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
            return response()->json($receipt->load('details.product'));
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to update sales receipt', 'message' => $e->getMessage()], 500);
        }
    }

    public function destroy(SalesReceipt $receipt)
    {
        DB::beginTransaction();
        try {
            // Delete file if exists
            if ($receipt->receipt_image_path) {
                Storage::disk('public')->delete($receipt->receipt_image_path);
            }

            // Delete details first
            $receipt->details()->delete();
            
            // Delete receipt
            $receipt->delete();

            DB::commit();
            return response()->json(['message' => 'Sales receipt deleted successfully']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to delete sales receipt', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Get next receipt number for sales receipts
     * Format: R + current year + 5 digits (e.g., R202500001)
     */
    public function getNextReceiptNumber()
    {
        $currentYear = date('Y');
        $prefix = 'R';
        
        // Check if receipt_number column exists
        $columnExists = Schema::hasColumn('sales_receipts', 'receipt_number');
        
        if (!$columnExists) {
            // If column doesn't exist yet (migration not run), return a default number
            $nextReceiptNumber = $prefix . $currentYear . str_pad(1, 5, '0', STR_PAD_LEFT);
            
            return response()->json([
                'receipt_number' => $nextReceiptNumber,
            ]);
        }
        
        try {
            // Get all receipt numbers for current year
            $receipts = SalesReceipt::where('receipt_number', 'like', $prefix . $currentYear . '%')
                ->pluck('receipt_number')
                ->toArray();
            
            $nextNumber = 1;
            
            // Find the highest sequential number
            foreach ($receipts as $receiptNumber) {
                // Check if receipt number matches pattern: R + YYYY + 5 digits
                if (preg_match('/^' . preg_quote($prefix) . $currentYear . '(\d{5})$/', $receiptNumber, $matches)) {
                    $number = intval($matches[1]);
                    if ($number >= $nextNumber) {
                        $nextNumber = $number + 1;
                    }
                }
            }
            
            // Format: R + YYYY + 5-digit number (e.g., R202500001)
            $nextReceiptNumber = $prefix . $currentYear . str_pad($nextNumber, 5, '0', STR_PAD_LEFT);
            
            // Double-check that this number doesn't exist
            $attempts = 0;
            while (SalesReceipt::where('receipt_number', $nextReceiptNumber)->exists() && $attempts < 10) {
                $nextNumber++;
                $nextReceiptNumber = $prefix . $currentYear . str_pad($nextNumber, 5, '0', STR_PAD_LEFT);
                $attempts++;
            }
            
            return response()->json([
                'receipt_number' => $nextReceiptNumber,
            ]);
        } catch (\Exception $e) {
            // If there's an error (e.g., column doesn't exist), return a default number
            \Log::warning('Error in getNextReceiptNumber: ' . $e->getMessage());
            $nextReceiptNumber = $prefix . $currentYear . str_pad(1, 5, '0', STR_PAD_LEFT);
            
            return response()->json([
                'receipt_number' => $nextReceiptNumber,
            ]);
        }
    }
}
