<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Distributor;
use Illuminate\Http\Request;

class DistributorController extends Controller
{
    public function index()
    {
        return response()->json(Distributor::all());
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
}
