<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductPrice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    public function index()
    {
        return response()->json(Product::with(['currentPrice', 'category', 'unit'])->get());
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'product_description' => 'nullable|string',
            'unit_id' => 'required|exists:units,id',
            'current_stock_quantity' => 'required|numeric|min:0',
            'wholesale_price' => 'required|numeric|min:0',
            'semi_wholesale_price' => 'required|numeric|min:0',
            'retail_price' => 'required|numeric|min:0',
            'tax_rate' => 'nullable|numeric|min:0|max:100',
            'barcode' => 'nullable|string|max:255',
            'category_id' => 'nullable|exists:categories,id',
        ]);

        DB::beginTransaction();
        try {
            $product = Product::create([
                'name' => $request->name,
                'product_description' => $request->product_description,
                'unit_id' => $request->unit_id,
                'current_stock_quantity' => $request->current_stock_quantity,
                'barcode' => $request->barcode,
                'category_id' => $request->category_id,
            ]);

            ProductPrice::create([
                'product_id' => $product->id,
                'wholesale_price' => $request->wholesale_price,
                'semi_wholesale_price' => $request->semi_wholesale_price,
                'retail_price' => $request->retail_price,
                'tax_rate' => $request->tax_rate ?? 0,
                'effective_date' => now(),
                'is_current' => true,
            ]);

            DB::commit();
            return response()->json($product->load('currentPrice', 'category', 'unit'), 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to create product: ' . $e->getMessage()], 500);
        }
    }

    public function show(Product $product)
    {
        return response()->json($product->load('currentPrice', 'category', 'unit'));
    }

    public function update(Request $request, Product $product)
    {
        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'product_description' => 'nullable|string',
            'unit_id' => 'sometimes|required|exists:units,id',
            'current_stock_quantity' => 'sometimes|required|numeric|min:0',
            'barcode' => 'nullable|string|max:255',
            'category_id' => 'nullable|exists:categories,id',
        ]);

        $product->update($request->only([
            'name',
            'product_description',
            'unit_id', 
            'current_stock_quantity',
            'barcode',
            'category_id'
        ]));
        
        return response()->json($product->load('currentPrice', 'category', 'unit'));
    }

    public function destroy(Product $product)
    {
        $product->delete();
        return response()->json(['message' => 'Product deleted successfully']);
    }

    public function updatePrices(Request $request, Product $product)
    {
        $request->validate([
            'wholesale_price' => 'required|numeric|min:0',
            'semi_wholesale_price' => 'required|numeric|min:0',
            'retail_price' => 'required|numeric|min:0',
            'tax_rate' => 'nullable|numeric|min:0|max:100',
            'effective_date' => 'nullable|date',
        ]);

        DB::beginTransaction();
        try {
            // Deactivate current price
            ProductPrice::where('product_id', $product->id)
                ->where('is_current', true)
                ->update(['is_current' => false]);

            // Create new price
            $newPrice = ProductPrice::create([
                'product_id' => $product->id,
                'wholesale_price' => $request->wholesale_price,
                'semi_wholesale_price' => $request->semi_wholesale_price,
                'retail_price' => $request->retail_price,
                'tax_rate' => $request->tax_rate ?? 0,
                'effective_date' => $request->effective_date ?? now(),
                'is_current' => true,
            ]);

            DB::commit();
            return response()->json($product->load('currentPrice', 'category', 'unit'));
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to update prices'], 500);
        }
    }
}
