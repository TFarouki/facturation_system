<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductSellingPrice;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PurchaseController extends Controller
{
    public function store(Request $request)
    {
        // Decode items if sent as JSON string (when using FormData with file upload)
        if (is_string($request->items)) {
            $request->merge(['items' => json_decode($request->items, true)]);
        }
        
        $request->validate([
            'supplier_id' => 'nullable|exists:suppliers,id',
            'supplier_name' => 'required|string|max:255',
            'invoice_number' => 'required|string|max:255|unique:purchase_invoices',
            'invoice_date' => 'required|date',
            'invoice_image' => 'nullable|image|max:5120',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.purchase_price' => 'required|numeric|min:0',
        ]);

        DB::beginTransaction();
        try {
            // Handle image upload
            $imagePath = null;
            if ($request->hasFile('invoice_image')) {
                $imagePath = $request->file('invoice_image')->store('invoices', 'public');
            }

            // Calculate total amount from items (Calculated Total)
            $calculatedTotal = collect($request->items)->sum(function ($item) {
                return $item['quantity'] * $item['purchase_price'];
            });
            
            // Get manual total from request (Total in Invoice)
            // If not provided, assume it matches calculated total
            $manualTotal = $request->filled('total_amount') ? $request->total_amount : $calculatedTotal;
            
            // Determine if there is a mismatch (difference > 0.01)
            $hasDifference = abs($manualTotal - $calculatedTotal) > 0.01;

            // Create invoice
            $invoice = PurchaseInvoice::create([
                'supplier_id' => $request->supplier_id,
                'supplier_name' => $request->supplier_name,
                'invoice_number' => $request->invoice_number,
                'invoice_date' => $request->invoice_date,
                'invoice_image_path' => $imagePath,
                'total_amount' => $calculatedTotal,      // Store calculated total here
                'total_in_invoice' => $manualTotal,      // Store declared/manual total here
                'it_has_def' => $hasDifference,          // Store mismatch flag
                'notes' => $request->notes,
            ]);

            // Create invoice details and update stock
            foreach ($request->items as $item) {
                PurchaseDetail::create([
                    'invoice_id' => $invoice->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'purchase_price' => $item['purchase_price'],
                ]);

                // Update product stock
                $product = Product::find($item['product_id']);
                $product->increment('current_stock_quantity', $item['quantity']);
            }

            DB::commit();

            return response()->json([
                'invoice' => $invoice->load(['details.product', 'supplier']),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            if ($imagePath) {
                Storage::disk('public')->delete($imagePath);
            }
            return response()->json(['error' => 'Failed to create purchase invoice', 'message' => $e->getMessage()], 500);
        }
    }

    public function index()
    {
        $invoices = PurchaseInvoice::with(['details.product.unit', 'supplier'])
            ->withCount('details')
            ->latest()
            ->get();
        return response()->json($invoices);
    }

    public function show(PurchaseInvoice $purchase)
    {
        return response()->json($purchase->load(['details.product.unit', 'supplier']));
    }

    public function update(Request $request, PurchaseInvoice $purchase)
    {
        // Decode items if sent as JSON string (when using FormData)
        if (is_string($request->items)) {
            $request->merge(['items' => json_decode($request->items, true)]);
        }
        
        $request->validate([
            'supplier_id' => 'nullable|exists:suppliers,id',
            'supplier_name' => 'required|string|max:255',
            'invoice_number' => 'required|string|max:255|unique:purchase_invoices,invoice_number,' . $purchase->id,
            'invoice_date' => 'required|date',
            'invoice_image' => 'nullable|image|max:5120',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.purchase_price' => 'required|numeric|min:0',
        ]);

        DB::beginTransaction();
        try {
            // Handle image upload
            $imagePath = $purchase->invoice_image_path;
            if ($request->hasFile('invoice_image')) {
                // Delete old image
                if ($imagePath) {
                    Storage::disk('public')->delete($imagePath);
                }
                $imagePath = $request->file('invoice_image')->store('invoices', 'public');
            }

            // Calculate totals
            $calculatedTotal = collect($request->items)->sum(function ($item) {
                return $item['quantity'] * $item['purchase_price'];
            });
            
            $manualTotal = $request->filled('total_amount') ? $request->total_amount : $calculatedTotal;
            $hasDifference = abs($manualTotal - $calculatedTotal) > 0.01;

            // Update invoice
            $purchase->update([
                'supplier_id' => $request->supplier_id,
                'supplier_name' => $request->supplier_name,
                'invoice_number' => $request->invoice_number,
                'invoice_date' => $request->invoice_date,
                'invoice_image_path' => $imagePath,
                'total_amount' => $calculatedTotal,
                'total_in_invoice' => $manualTotal,
                'it_has_def' => $hasDifference,
                'notes' => $request->notes,
            ]);

            // Delete old details
            $purchase->details()->delete();

            // Create new details and update stock
            foreach ($request->items as $item) {
                PurchaseDetail::create([
                    'invoice_id' => $purchase->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'purchase_price' => $item['purchase_price'],
                ]);

                // Update product stock
                $product = Product::find($item['product_id']);
                $product->increment('current_stock_quantity', $item['quantity']);
            }

            DB::commit();

            return response()->json([
                'invoice' => $purchase->load(['details.product', 'supplier']),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            if ($imagePath && $imagePath !== $purchase->invoice_image_path) {
                Storage::disk('public')->delete($imagePath);
            }
            return response()->json(['error' => 'Failed to update purchase invoice', 'message' => $e->getMessage()], 500);
        }
    }

    public function destroy(PurchaseInvoice $purchase)
    {
        \Illuminate\Support\Facades\Log::info('Destroy called for invoice ID: ' . $purchase->id);
        DB::beginTransaction();
        try {
            // Soft delete all invoice details (soft deletion)
            $purchase->details()->delete();
            
            // Soft delete the invoice itself
            $purchase->delete();
            
            DB::commit();
            return response()->json(['message' => 'Invoice deleted successfully']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to delete invoice: ' . $e->getMessage()], 500);
        }
    }

    public function attachFile(Request $request, PurchaseInvoice $purchase)
    {
        $request->validate([
            'invoice_image' => 'required|file|mimes:jpeg,png,jpg,pdf|max:5120',
        ]);

        try {
            // Delete old image if exists
            if ($purchase->invoice_image_path) {
                Storage::disk('public')->delete($purchase->invoice_image_path);
            }

            // Store new image
            $imagePath = $request->file('invoice_image')->store('invoices', 'public');
            
            // Update invoice
            $purchase->invoice_image_path = $imagePath;
            $purchase->save();

            return response()->json([
                'message' => 'Invoice document uploaded successfully',
                'invoice' => $purchase
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to upload document: ' . $e->getMessage()], 500);
        }
    }
}
