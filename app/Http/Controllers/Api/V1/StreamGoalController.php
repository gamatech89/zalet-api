<?php

namespace App\Http\Controllers\Api\V1;

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

}
