<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProductFamily;
use Illuminate\Http\Request;

class ProductFamilyController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = ProductFamily::withCount('products');
        
        if ($request->has('q')) {
            $search = $request->q;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('name_ar', 'like', "%{$search}%");
            });
        }
        
        return response()->json($query->latest()->get());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'name_ar' => 'nullable|string|max:255',
            'description' => 'nullable|string',
        ]);

        $family = ProductFamily::create($request->all());

        return response()->json($family, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(ProductFamily $productFamily)
    {
        return response()->json($productFamily->load('products'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, ProductFamily $productFamily)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'name_ar' => 'nullable|string|max:255',
            'description' => 'nullable|string',
        ]);

        $productFamily->update($request->all());

        return response()->json($productFamily);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ProductFamily $productFamily)
    {
        // Check if family has products
        if ($productFamily->products()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete family with associated products'
            ], 422);
        }

        $productFamily->delete();

        return response()->json(['message' => 'Product family deleted successfully']);
    }

    /**
     * Search product families for autocomplete
     */
    public function search(Request $request)
    {
        $query = $request->get('q', '');
        
        $families = ProductFamily::where('name', 'like', "%{$query}%")
            ->orWhere('name_ar', 'like', "%{$query}%")
            ->limit(10)
            ->get();

        return response()->json($families);
    }
}

