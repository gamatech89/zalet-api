<?php

namespace App\Http\Controllers\Api\V1;

use App\Events\StreamChatMessage;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreateStreamRequest;
use App\Models\LiveStream;
use App\Models\StreamUnlock;
use App\Services\CoinService;
use App\Services\LiveKitService;
use App\Services\LiveStreamService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class LiveStreamController extends Controller
{
    public function __construct(
        private LiveKitService $liveKit,
        private LiveStreamService $liveStreamService,
    ) {}

    /**
     * Create a new live stream.
     * POST /api/v1/streams
     */
    public function store(CreateStreamRequest $request): JsonResponse
    {
        $user = $request->user();

        // Check if user already has an active stream
        $existingStream = $user->liveStreams()->where('is_live', true)->first();
        if ($existingStream) {
            return response()->json([
                'message' => 'You already have an active stream. Stop it before creating a new one.',
                'stream_id' => $existingStream->id,
            ], 409);
        }

        $streamMode = $request->input('stream_mode', 'scena');

        // Optionally activate an existing scheduled stream instead of creating a new one
        if ($request->filled('stream_id')) {
            $stream = $user->liveStreams()
                ->where('id', $request->stream_id)
                ->where('is_live', false)
                ->whereNull('livekit_room_name')
                ->firstOrFail();
            $stream->update([
                'title'       => $request->title,
                'stream_mode' => $streamMode,
                'entry_price' => $request->input('entry_price') ?: null,
            ]);
        } else {
            $stream = $user->liveStreams()->create([
                'title'       => $request->title,
                'stream_mode' => $streamMode,
                'entry_price' => $request->input('entry_price') ?: null,
            ]);
        }

        // Create LiveKit room
        $roomName = $this->liveKit->createRoom($stream);
        $stream->update(['livekit_room_name' => $roomName]);

        // Generate publisher token for WebRTC camera streaming
        $publisherToken = $this->liveKit->isConfigured()
            ? $this->liveKit->generatePublisherToken($stream, 'streamer-' . $user->id, $user->username)
            : null;

        return response()->json([
            'message' => 'Stream created successfully.',
            'data' => [
                'id' => $stream->id,
                'title' => $stream->title,
                'stream_key' => $stream->stream_key,
                'stream_mode' => $stream->stream_mode,
                'is_live' => $stream->is_live,
                'entry_price' => $stream->entry_price,
                'livekit_token' => $publisherToken,
                'livekit_ws_url' => $this->liveKit->getWsUrl(),
                'created_at' => $stream->created_at->toIso8601String(),
            ],
        ], 201);
    }

    /**
     * Get the stream key for the authenticated user's stream.
     * GET /api/v1/streams/key
     */
    public function getStreamKey(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->isCreator()) {
            return response()->json([
                'message' => 'Only creators can access stream keys.',
            ], 403);
        }

        // Get the most recent non-live stream, or provision a key stream on first access
        $stream = $user->liveStreams()->latest()->first();

        if (!$stream) {
            $stream = $user->liveStreams()->create([
                'title' => 'Moj stream',
                'stream_mode' => 'scena',
            ]);
        }

        return response()->json([
            'data' => [
                'id' => $stream->id,
                'title' => $stream->title,
                'stream_key' => $stream->stream_key,
                'stream_mode' => $stream->stream_mode,
                'is_live' => $stream->is_live,
            ],
        ]);
    }

    /**
     * Start broadcasting the stream.
     * POST /api/v1/streams/start
     */
    public function start(Request $request): JsonResponse
    {
        $data = $this->liveStreamService->startStream($request->user());

        return response()->json([
            'message' => 'Stream started successfully.',
            'data'    => $data,
        ]);
    }

    /**
     * Stop broadcasting the stream.
     * POST /api/v1/streams/stop
     */
    public function stop(Request $request): JsonResponse
    {
        $data = $this->liveStreamService->stopStream($request->user());

        return response()->json([
            'message' => 'Stream stopped successfully.',
            'data'    => $data,
        ]);
    }

    /**
     * Upload a thumbnail for the stream (captured from camera front-end).
     * POST /api/v1/streams/{liveStream}/thumbnail
     */
    public function uploadThumbnail(Request $request, LiveStream $liveStream): JsonResponse
    {
        $this->authorize('update', $liveStream);

        $request->validate([
            'thumbnail' => ['required', 'image', 'mimes:jpeg,png,webp', 'max:5120'], // max 5MB
        ]);

        // Delete the previous thumbnail file to prevent accumulation
        if ($liveStream->thumbnail_url) {
            Storage::disk('public')->delete($liveStream->thumbnail_url);
        }

        $path = $request->file('thumbnail')->store('live-thumbnails', 'public');

        $liveStream->update([
            'thumbnail_url' => $path,
        ]);

        return response()->json([
            'message' => 'Thumbnail uploaded.',
            'data' => [
                'thumbnail_url' => asset('storage/' . $path),
            ]
        ]);
    }

    /**
     * List all upcoming scheduled streams (public).
     * GET /api/v1/streams/upcoming
     */
    public function upcoming(): JsonResponse
    {
        $streams = LiveStream::whereNotNull('scheduled_at')
            ->where('scheduled_at', '>', now())
            ->where('is_live', false)
            ->with('user:id,username')
            ->orderBy('scheduled_at')
            ->limit(20)
            ->get();

        return response()->json([
            'data' => $streams->map(fn ($s) => [
                'id'           => $s->id,
                'title'        => $s->title,
                'stream_mode'  => $s->stream_mode,
                'scheduled_at' => $s->scheduled_at->toIso8601String(),
                'streamer'     => ['id' => $s->user->id, 'username' => $s->user->username],
            ]),
        ]);
    }

    /**
     * List all currently live streams.
     * GET /api/v1/streams/live
     */
    public function live(Request $request): JsonResponse
    {
        $mode = $request->query('mode');

        $streams = LiveStream::live()
            ->byMode($mode)
            ->with(['user:id,username', 'user.profile:user_id,avatar_url', 'currentSession'])
            ->orderBy('updated_at', 'desc')
            ->paginate(20);

        return response()->json([
            'data' => $streams->map(function ($stream) {
                return [
                    'id' => $stream->id,
                    'title' => $stream->title,
                    'stream_mode' => $stream->stream_mode,
                    'thumbnail_url' => $stream->thumbnail_url ? asset('storage/' . $stream->thumbnail_url) : null,
                    'streamer' => [
                        'id' => $stream->user->id,
                        'username' => $stream->user->username,
                        'avatar_url' => $stream->user->profile?->avatar_url ?? null,
                    ],
                    'viewers' => $stream->currentSession?->current_viewers ?? 0,
                    'started_at' => $stream->currentSession?->start_time?->toIso8601String(),
                ];
            }),
            'meta' => [
                'current_page' => $streams->currentPage(),
                'last_page' => $streams->lastPage(),
                'per_page' => $streams->perPage(),
                'total' => $streams->total(),
            ],
        ]);
    }

    /**
     * Get a viewer token for watching a stream.
     * GET /api/v1/streams/{liveStream}/token
     */
    public function viewerToken(Request $request, LiveStream $liveStream): JsonResponse
    {
        // Route is public so auth middleware hasn't run — resolve via Sanctum explicitly
        $user = $request->user() ?? auth('sanctum')->user();

        if (!$liveStream->is_live) {
            return response()->json([
                'message' => 'This stream is not currently live.',
            ], 422);
        }

        $hasEntryPrice = $liveStream->entry_price && $liveStream->entry_price > 0;
        if ($hasEntryPrice && (!$user || $user->id !== $liveStream->user_id)) {
            $isUnlocked = $user && (
                $user->hasSubscriptionLevel(1)
                || StreamUnlock::where('user_id', $user->id)->where('live_stream_id', $liveStream->id)->exists()
            );
            if (!$isUnlocked) {
                return response()->json([
                    'message' => 'Ovaj stream zahteva otključavanje kovanicama.',
                    'entry_price' => $liveStream->entry_price,
                ], 403);
            }
        }

        $identity = $user ? 'viewer-' . $user->id : 'guest-' . Str::random(8);
        $name = $user ? $user->username : 'Guest ' . Str::random(4);

        $token = $this->liveKit->isConfigured()
            ? $this->liveKit->generateViewerToken($liveStream, $identity, $name)
            : null;

        return response()->json([
            'data' => [
                'token' => $token,
                'ws_url' => $this->liveKit->getWsUrl(),
                'room_name' => $liveStream->livekit_room_name,
                'stream' => [
                    'id' => $liveStream->id,
                    'title' => $liveStream->title,
                    'stream_mode' => $liveStream->stream_mode,
                    'thumbnail_url' => $liveStream->thumbnail_url ? asset('storage/' . $liveStream->thumbnail_url) : null,
                    'streamer' => [
                        'id' => $liveStream->user->id,
                        'username' => $liveStream->user->username,
                    ],
                    'viewers' => $liveStream->currentSession?->current_viewers ?? 0,
                    'started_at' => $liveStream->currentSession?->start_time?->toIso8601String(),
                ],
            ],
        ]);
    }

    /**
     * Pay a live stream's entry_price to unlock viewing it.
     * POST /api/v1/streams/{liveStream}/unlock
     */
    public function unlock(Request $request, LiveStream $liveStream, CoinService $coinService): JsonResponse
    {
        $user = $request->user();

        if (!$liveStream->entry_price || $liveStream->entry_price <= 0) {
            return response()->json(['message' => 'This stream has no entry price.'], 422);
        }

        if ($user->id === $liveStream->user_id) {
            return response()->json(['message' => 'You already own this stream.'], 422);
        }

        if ($user->hasSubscriptionLevel(1)) {
            return response()->json(['message' => 'Premium/VIP korisnici gledaju besplatno.', 'already_unlocked' => true]);
        }

        if (StreamUnlock::where('user_id', $user->id)->where('live_stream_id', $liveStream->id)->exists()) {
            return response()->json(['message' => 'Stream je već otključan.', 'already_unlocked' => true]);
        }

        try {
            $coinService->purchaseStreamEntry($user, $liveStream);
        } catch (\RuntimeException $e) {
            return response()->json([
                'message' => "Nedovoljno kovanica. Trebaš {$liveStream->entry_price} ZC za pristup streamu.",
                'balance_required' => $liveStream->entry_price,
            ], 422);
        }

        StreamUnlock::create(['user_id' => $user->id, 'live_stream_id' => $liveStream->id]);

        return response()->json(['message' => 'Stream otključan!']);
    }

    /**
     * Send a chat message in a live stream.
     * POST /api/v1/streams/{liveStream}/chat
     */
    public function sendChat(Request $request, LiveStream $liveStream): JsonResponse
    {
        $request->validate([
            'message' => ['required', 'string', 'max:500'],
        ]);

        $user = $request->user();

        if (!$liveStream->is_live) {
            return response()->json([
                'message' => 'This stream is not currently live.',
            ], 422);
        }

        // Broadcast via Reverb
        broadcast(new StreamChatMessage(
            $liveStream->id,
            $user,
            $request->input('message')
        ));

        return response()->json([
            'message' => 'Message sent.',
            'data' => [
                'id' => (string) Str::uuid(),
                'user' => [
                    'id' => $user->id,
                    'username' => $user->username,
                ],
                'message' => $request->input('message'),
                'timestamp' => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * Get details of a specific stream.
     * GET /api/v1/streams/{liveStream}
     */
    public function show(Request $request, LiveStream $liveStream): JsonResponse
    {
        $liveStream->load(['user:id,username', 'user.profile:user_id,avatar_url', 'currentSession']);

        $user = $request->user() ?? auth('sanctum')->user();
        $hasEntryPrice = $liveStream->entry_price && $liveStream->entry_price > 0;
        $isUnlocked = !$hasEntryPrice
            || ($user && $user->id === $liveStream->user_id)
            || ($user && $user->hasSubscriptionLevel(1))
            || ($user && StreamUnlock::where('user_id', $user->id)->where('live_stream_id', $liveStream->id)->exists());

        return response()->json([
            'data' => [
                'id' => $liveStream->id,
                'title' => $liveStream->title,
                'entry_price' => $liveStream->entry_price,
                'is_unlocked' => $isUnlocked,
                'stream_mode' => $liveStream->stream_mode,
                'thumbnail_url' => $liveStream->thumbnail_url ? asset('storage/' . $liveStream->thumbnail_url) : null,
                'is_live' => $liveStream->is_live,
                'has_recording' => $liveStream->has_recording,
                'recording_url' => $liveStream->recording_full_url,
                'recording_duration' => $liveStream->recording_duration,
                'streamer' => [
                    'id' => $liveStream->user->id,
                    'username' => $liveStream->user->username,
                    'avatar_url' => $liveStream->user->profile?->avatar_url,
                ],
                'scheduled_at' => $liveStream->scheduled_at?->toIso8601String(),
                'viewers' => $liveStream->currentSession?->current_viewers ?? 0,
                'started_at' => $liveStream->currentSession?->start_time?->toIso8601String(),
                'livekit_ws_url' => $this->liveKit->getWsUrl(),
                'goals' => $liveStream->goals ?? [],
                'chat_enabled' => $liveStream->chat_enabled ?? true,
            ],
        ]);
    }

    /**
     * Upload a stream recording.
     * POST /api/v1/streams/{liveStream}/recording
     */
    public function uploadRecording(Request $request, LiveStream $liveStream): JsonResponse
    {
        $this->authorize('update', $liveStream);

        $request->validate([
            'recording' => 'required|file|mimetypes:video/webm,video/mp4,video/x-matroska|max:2097152', // 2 GB max
            'duration' => 'nullable|integer|min:1',
        ]);

        $file = $request->file('recording');
        $filename = 'stream-' . $liveStream->id . '-' . time() . '.' . $file->getClientOriginalExtension();

        $path = $file->storeAs('streams/' . $liveStream->id, $filename, 's3');

        $liveStream->update([
            'recording_url' => $path,
            'recording_disk' => 's3',
            'recording_duration' => $request->input('duration'),
            'recording_size' => $file->getSize(),
            'has_recording' => true,
        ]);

        return response()->json([
            'message' => 'Recording saved successfully.',
            'data' => [
                'recording_url' => $liveStream->fresh()->recording_full_url,
                'recording_size' => $file->getSize(),
            ],
        ]);
    }

    /**
     * Discard a stream recording.
     * DELETE /api/v1/streams/{liveStream}/recording
     */
    public function discardRecording(Request $request, LiveStream $liveStream): JsonResponse
    {
        $this->authorize('update', $liveStream);

        $liveStream->deleteRecording();

        return response()->json([
            'message' => 'Recording discarded.',
        ]);
    }
}
