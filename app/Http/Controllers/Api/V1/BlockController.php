<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Block;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BlockController extends Controller
{
    /**
     * List users blocked by the authenticated user.
     * GET /api/v1/blocks
     */
    public function index(Request $request): JsonResponse
    {
        $blocks = Block::where('blocker_id', $request->user()->id)
            ->with('blocked:id,username,name')
            ->with('blocked.profile:user_id,avatar_url')
            ->latest()
            ->get()
            ->map(fn($b) => [
                'id' => $b->blocked->id,
                'username' => $b->blocked->username,
                'name' => $b->blocked->name,
                'avatar_url' => $b->blocked->profile?->avatar_url,
                'blocked_at' => $b->created_at->toIso8601String(),
            ]);

        return response()->json(['data' => $blocks]);
    }

    /**
     * Block a user.
     * POST /api/v1/blocks
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'uuid', 'exists:users,id'],
        ]);

        if ($validated['user_id'] === $request->user()->id) {
            return response()->json(['message' => 'Ne možeš blokirati sam sebe.'], 422);
        }

        Block::firstOrCreate([
            'blocker_id' => $request->user()->id,
            'blocked_id' => $validated['user_id'],
        ]);

        return response()->json(['message' => 'Korisnik je blokiran.'], 201);
    }

    /**
     * Unblock a user.
     * DELETE /api/v1/blocks/{user}
     */
    public function destroy(Request $request, User $user): JsonResponse
    {
        Block::where('blocker_id', $request->user()->id)
            ->where('blocked_id', $user->id)
            ->delete();

        return response()->json(['message' => 'Korisnik je odblokiran.']);
    }

    /**
     * Check if a specific user is blocked.
     * GET /api/v1/blocks/{user}
     */
    public function check(Request $request, User $user): JsonResponse
    {
        $isBlocked = Block::where('blocker_id', $request->user()->id)
            ->where('blocked_id', $user->id)
            ->exists();

        return response()->json(['is_blocked' => $isBlocked]);
    }
}
