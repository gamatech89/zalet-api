<?php

namespace App\Http\Controllers\Api\V1;

use App\Events\StreamGoalsReplacedEvent;
use App\Http\Controllers\Controller;
use App\Models\LiveStream;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class StreamGoalController extends Controller
{
    /**
     * Set goals for a live stream (replaces the list, preserves progress
     * for goals whose id — or, for legacy id-less goals, description —
     * matches an existing goal).
     * PUT /api/v1/streams/{liveStream}/goals
     */
    public function update(Request $request, LiveStream $liveStream): JsonResponse
    {
        abort_if($liveStream->user_id !== $request->user()->id, 403);

        $request->validate([
            'goals'                => 'present|array|max:5',
            'goals.*.id'           => 'nullable|string|max:64',
            'goals.*.description'  => 'required|string|max:120',
            'goals.*.target_coins' => 'required|integer|min:1',
        ]);

        $existing = collect($liveStream->goals ?? []);

        $goals = collect($request->goals)->map(function ($g) use ($existing) {
            $match = !empty($g['id']) ? $existing->firstWhere('id', $g['id']) : null;
            // Legacy goals saved before ids existed — match by description
            if (!$match) {
                $match = $existing->first(
                    fn ($e) => empty($e['id']) && $e['description'] === $g['description']
                );
            }
            return [
                'id'            => $g['id'] ?? $match['id'] ?? (string) Str::uuid(),
                'description'   => $g['description'],
                'target_coins'  => (int) $g['target_coins'],
                'current_coins' => (int) ($match['current_coins'] ?? 0),
            ];
        })->values()->all();

        $liveStream->update(['goals' => $goals]);

        broadcast(new StreamGoalsReplacedEvent($liveStream->fresh()));

        return response()->json(['data' => $liveStream->goals]);
    }
}
