<?php

namespace App\Http\Controllers\Api\V1;

use App\Events\StreamGoalUpdatedEvent;
use App\Http\Controllers\Controller;
use App\Models\LiveStream;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StreamGoalController extends Controller
{
    /**
     * Set goals for a live stream (replaces all).
     * PUT /api/v1/streams/{liveStream}/goals
     */
    public function update(Request $request, LiveStream $liveStream): JsonResponse
    {
        abort_if($liveStream->user_id !== $request->user()->id, 403);

        $request->validate([
            'goals'                       => 'required|array|max:5',
            'goals.*.description'         => 'required|string|max:120',
            'goals.*.target_coins'        => 'required|integer|min:1',
        ]);

        $goals = collect($request->goals)->map(fn($g) => [
            'description'  => $g['description'],
            'target_coins' => (int) $g['target_coins'],
            'current_coins' => 0,
        ])->all();

        $liveStream->update(['goals' => $goals]);

        return response()->json(['data' => $liveStream->goals]);
    }

    /**
     * Increment a goal's current_coins (called internally when a gift is sent).
     * POST /api/v1/streams/{liveStream}/goals/{index}/progress
     */
    public function progress(Request $request, LiveStream $liveStream, int $index): JsonResponse
    {
        abort_if($liveStream->user_id !== $request->user()->id, 403);

        $goals = $liveStream->goals ?? [];
        if (!isset($goals[$index])) {
            return response()->json(['message' => 'Goal not found.'], 404);
        }

        $request->validate(['coins' => 'required|integer|min:1']);

        $wasDone = $goals[$index]['current_coins'] >= $goals[$index]['target_coins'];
        $goals[$index]['current_coins'] = min(
            $goals[$index]['current_coins'] + $request->coins,
            $goals[$index]['target_coins']
        );
        $isNowDone = $goals[$index]['current_coins'] >= $goals[$index]['target_coins'];

        $liveStream->update(['goals' => $goals]);

        // Broadcast update; mark as completed if just crossed threshold
        $completed = !$wasDone && $isNowDone;
        broadcast(new StreamGoalUpdatedEvent($liveStream->fresh(), $index, $completed));

        return response()->json(['data' => $goals]);
    }
}
