<?php

namespace App\Http\Controllers;

use App\Services\LiveKitService;
use App\Domains\Streaming\Models\StreamSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LiveKitController extends Controller
{
    public function __construct(
        private LiveKitService $liveKitService
    ) {}

    /**
     * Get token for starting a stream (streamer)
     */
    public function getStreamerToken(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'room_name' => 'required|string|max:100',
        ]);

        $user = $request->user();
        $tokenData = $this->liveKitService->generateStreamerToken($user, $validated['room_name']);

        return response()->json([
            'data' => $tokenData,
        ]);
    }

    /**
     * Get token for watching a stream (viewer)
     */
    public function getViewerToken(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'room_name' => 'required|string|max:100',
        ]);

        $user = $request->user();
        $tokenData = $this->liveKitService->generateViewerToken($user, $validated['room_name']);

        return response()->json([
            'data' => $tokenData,
        ]);
    }

    /**
     * Get token for a specific stream session
     */
    public function getStreamToken(Request $request, StreamSession $session): JsonResponse
    {
        $user = $request->user();
        $roomName = $this->liveKitService->generateRoomName($session);

        // Check if user is the streamer
        if ($session->user_id === $user->id) {
            $tokenData = $this->liveKitService->generateStreamerToken($user, $roomName);
        } else {
            $tokenData = $this->liveKitService->generateViewerToken($user, $roomName);
        }

        return response()->json([
            'data' => array_merge($tokenData, [
                'is_streamer' => $session->user_id === $user->id,
                'session_id' => $session->id,
            ]),
        ]);
    }

    /**
     * Get LiveKit server info (for client connection)
     */
    public function getServerInfo(): JsonResponse
    {
        return response()->json([
            'data' => $this->liveKitService->getServerInfo(),
        ]);
    }

    /**
     * Create a new stream and get token
     */
    public function createStream(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:200',
            'description' => 'nullable|string|max:1000',
            'category' => 'nullable|string|max:50',
            'thumbnail_url' => 'nullable|url',
            'is_public' => 'boolean',
        ]);

        $user = $request->user();

        // Create stream session
        $session = StreamSession::create([
            'user_id' => $user->id,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'category' => $validated['category'] ?? 'other',
            'thumbnail_url' => $validated['thumbnail_url'] ?? null,
            'is_public' => $validated['is_public'] ?? true,
            'status' => 'pending',
        ]);

        $roomName = $this->liveKitService->generateRoomName($session);
        
        // Update session with room name
        $session->update(['room_name' => $roomName]);

        $tokenData = $this->liveKitService->generateStreamerToken($user, $roomName);

        return response()->json([
            'data' => array_merge($tokenData, [
                'session' => $session->load('user.profile'),
            ]),
        ], 201);
    }

    /**
     * Start streaming (update status to live)
     */
    public function startStream(Request $request, StreamSession $session): JsonResponse
    {
        if ($session->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $session->update([
            'status' => 'live',
            'started_at' => now(),
        ]);

        return response()->json([
            'data' => $session->fresh()->load('user.profile'),
            'message' => 'Stream started!',
        ]);
    }

    /**
     * End streaming
     */
    public function endStream(Request $request, StreamSession $session): JsonResponse
    {
        if ($session->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $session->update([
            'status' => 'ended',
            'ended_at' => now(),
        ]);

        return response()->json([
            'data' => $session->fresh(),
            'message' => 'Stream ended!',
        ]);
    }

    /**
     * Get active/live streams
     */
    public function getLiveStreams(): JsonResponse
    {
        $streams = StreamSession::where('status', 'live')
            ->where('is_public', true)
            ->with('user.profile')
            ->orderByDesc('viewer_count')
            ->limit(50)
            ->get();

        return response()->json([
            'data' => $streams,
        ]);
    }
}
