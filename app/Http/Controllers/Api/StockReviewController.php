<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StockReview;
use App\Models\StockReviewDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockReviewController extends Controller
{
    /**
     * Get all stock reviews
     */
    public function index(Request $request)
    {
        $query = StockReview::with(['distributor', 'details.product', 'confirmedBy']);
        
        if ($request->has('distributor_id')) {
            $query->where('distributor_id', $request->distributor_id);
        }
        
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        
        return $query->orderBy('created_at', 'desc')->get();
    }

    /**
     * Store a new stock review
     */
    public function store(Request $request)
    {
        $request->validate([
            'distributor_id' => 'required|exists:distributors,id',
            'review_date' => 'required|date',
            'differences' => 'present|array',
            'differences.*.product_id' => 'required_with:differences|exists:products,id',
            'differences.*.committed_quantity' => 'required_with:differences|numeric',
            'differences.*.actual_quantity' => 'required_with:differences|numeric',
            'differences.*.difference' => 'required_with:differences|numeric',
            'notes' => 'nullable|string',
        ]);

        return DB::transaction(function () use ($request) {
            // Cancel any existing pending reviews for this distributor
            StockReview::where('distributor_id', $request->distributor_id)
                ->where('status', 'pending')
                ->update(['status' => 'cancelled']);

            $hasDifferences = count($request->differences) > 0;

            // Create new review
            // If no differences, automatically mark as confirmed
            $review = StockReview::create([
                'distributor_id' => $request->distributor_id,
                'review_date' => $request->review_date,
                'status' => $hasDifferences ? 'pending' : 'confirmed',
                'notes' => $request->notes,
                'confirmed_by' => $hasDifferences ? null : auth()->id(),
                'confirmed_at' => $hasDifferences ? null : now(),
            ]);

            // Create details only if there are differences
            foreach ($request->differences as $diff) {
                StockReviewDetail::create([
                    'stock_review_id' => $review->id,
                    'product_id' => $diff['product_id'],
                    'committed_quantity' => $diff['committed_quantity'],
                    'actual_quantity' => $diff['actual_quantity'],
                    'difference' => $diff['difference'],
                ]);
            }

            return $review->load(['distributor', 'details.product']);
        });
    }

    /**
     * Get pending review for a distributor
     */
    public function getPending($distributorId)
    {
        $review = StockReview::where('distributor_id', $distributorId)
            ->where('status', 'pending')
            ->with('details')
            ->first();

        if (!$review) {
            return response()->json([]);
        }

        // Return as { productId: actualQuantity }
        $result = [];
        foreach ($review->details as $detail) {
            $result[$detail->product_id] = floatval($detail->actual_quantity);
        }

        return response()->json($result);
    }

    /**
     * Confirm a stock review
     */
    public function confirm(Request $request, StockReview $stockReview)
    {
        if ($stockReview->status !== 'pending') {
            return response()->json(['message' => 'Review is not pending'], 400);
        }

        $stockReview->update([
            'status' => 'confirmed',
            'confirmed_by' => auth()->id(),
            'confirmed_at' => now(),
        ]);

        return $stockReview->load(['distributor', 'details.product', 'confirmedBy']);
    }

    /**
     * Cancel a stock review
     */
    public function cancel(StockReview $stockReview)
    {
        if ($stockReview->status !== 'pending') {
            return response()->json(['message' => 'Review is not pending'], 400);
        }

        $stockReview->update(['status' => 'cancelled']);

        return $stockReview;
    }

    /**
     * Get a specific stock review
     */
    public function show(StockReview $stockReview)
    {
        return $stockReview->load(['distributor', 'details.product', 'confirmedBy']);
    }

    /**
     * Delete a stock review
     */
    public function destroy(StockReview $stockReview)
    {
        if ($stockReview->status === 'confirmed') {
            return response()->json(['message' => 'Cannot delete confirmed review'], 400);
        }

        $stockReview->delete();

        return response()->json(['message' => 'Review deleted successfully']);
    }
}

