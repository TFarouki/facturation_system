<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Unit;
use Illuminate\Http\Request;

class UnitController extends Controller
{
    public function index()
    {
        return response()->json(Unit::all());
    }

    public function store(Request $request)
    {
        $request->validate([
            'unit_name_ar' => 'required|string|max:255',
            'unit_symbol_en' => 'nullable|string|max:50',
            'description' => 'nullable|string',
        ]);

        $unit = Unit::create($request->all());
        return response()->json($unit, 201);
    }
}
