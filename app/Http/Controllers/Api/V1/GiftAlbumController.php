<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Gift;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GiftAlbumController extends Controller
{
    /**
     * Get the user's gift collection album.
     * Shows all gifts with sent_count and received_count.
     * 
     * GET /api/v1/gifts/album
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // Get the user's wallet
        $wallet = $user->wallet;
        if (!$wallet) {
            // User hasn't sent/received anything yet
            $walletId = null;
        } else {
            $walletId = $wallet->id;
        }

        // Aggregate sent gifts
        $sentCounts = [];
        if ($walletId) {
            $sentCounts = Transaction::query()
                ->where('from_wallet_id', $walletId)
                ->whereNotNull('gift_id')
                ->where('type', 'tip') // Only count actual gifts/tips
                ->select('gift_id', DB::raw('count(*) as count'))
                ->groupBy('gift_id')
                ->pluck('count', 'gift_id')
                ->toArray();
        }

        // Aggregate received gifts
        $receivedCounts = [];
        if ($walletId) {
            $receivedCounts = Transaction::query()
                ->where('to_wallet_id', $walletId)
                ->whereNotNull('gift_id')
                ->where('type', 'tip')
                ->select('gift_id', DB::raw('count(*) as count'))
                ->groupBy('gift_id')
                ->pluck('count', 'gift_id')
                ->toArray();
        }

        // Fetch all active gifts
        $gifts = Gift::active()
            ->with('category')
            ->ordered()
            ->get();

        // Map counts onto gifts
        $album = $gifts->map(function ($gift) use ($sentCounts, $receivedCounts) {
            $sentCount = $sentCounts[$gift->id] ?? 0;
            $receivedCount = $receivedCounts[$gift->id] ?? 0;

            return [
                'id' => $gift->id,
                'name' => $gift->name,
                'coin_price' => $gift->coin_price,
                'icon_url' => $gift->icon_url,
                'icon_2d' => $gift->icon_2d,
                'icon_3d' => $gift->icon_3d,
                'category_id' => $gift->category_id,
                'category_slug' => $gift->category?->slug,
                'level' => $gift->level,
                'is_epic' => (bool) $gift->is_epic,
                'is_rare' => (bool) $gift->is_rare,
                'description' => $gift->description,
                'sort_order' => $gift->sort_order,
                'sent_count' => $sentCount,
                'received_count' => $receivedCount,
                // A gift is unlocked if they have sent or received it at least once.
                'unlocked_at' => ($sentCount > 0 || $receivedCount > 0) ? now()->toIso8601String() : null,
            ];
        });

        return response()->json([
            'data' => $album
        ]);
    }
}
