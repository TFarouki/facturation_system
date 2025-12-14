<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
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

        return $query->orderBy('name')->get();
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
}
