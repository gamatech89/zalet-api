<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\LiveStream;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StreamScheduleController extends Controller
{
    /**
     * List scheduled (future, not yet live) streams for the creator.
     * GET /api/v1/streams/scheduled
     */
    public function index(Request $request): JsonResponse
    {
        $streams = LiveStream::where('user_id', $request->user()->id)
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '>', now())
            ->where('is_live', false)
            ->orderBy('scheduled_at')
            ->get(['id', 'title', 'stream_mode', 'scheduled_at', 'thumbnail_url', 'chat_enabled']);

        return response()->json(['data' => $streams]);
    }

    /**
     * Schedule a new stream.
     * POST /api/v1/streams/schedule
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title'       => 'required|string|max:100',
            'stream_mode' => 'required|in:scena,moments',
            'scheduled_at' => 'required|date|after:now',
            'chat_enabled' => 'boolean',
        ]);

        $stream = LiveStream::create([
            'user_id'      => $request->user()->id,
            'title'        => $validated['title'],
            'stream_mode'  => $validated['stream_mode'],
            'scheduled_at' => $validated['scheduled_at'],
            'chat_enabled' => $validated['chat_enabled'] ?? true,
            'is_live'      => false,
        ]);

        return response()->json(['data' => $stream->only(['id', 'title', 'stream_mode', 'scheduled_at', 'thumbnail_url', 'chat_enabled'])], 201);
    }

    /**
     * Delete a scheduled stream.
     * DELETE /api/v1/streams/schedule/{stream}
     */
    public function destroy(Request $request, LiveStream $stream): JsonResponse
    {
        abort_if($stream->user_id !== $request->user()->id, 403);
        abort_if($stream->is_live, 422, 'Cannot delete a live stream.');

        $stream->delete();

        return response()->json(['message' => 'Scheduled stream deleted.']);
    }
}
