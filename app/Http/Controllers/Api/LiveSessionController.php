<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domains\Duel\Actions\CreateLiveSessionAction;
use App\Domains\Duel\Actions\EndLiveSessionAction;
use App\Domains\Duel\Actions\JoinLiveSessionAction;
use App\Domains\Duel\Actions\SendDuelGiftAction;
use App\Domains\Duel\Enums\LiveSessionStatus;
use App\Domains\Duel\Models\ChatRoom;
use App\Domains\Duel\Models\LiveSession;
use App\Domains\Duel\Resources\DuelEventResource;
use App\Domains\Duel\Resources\LiveSessionResource;
use App\Domains\Duel\Services\DuelScoreService;
use App\Domains\Identity\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class LiveSessionController extends Controller
{
    public function __construct(
        private readonly CreateLiveSessionAction $createSession,
        private readonly JoinLiveSessionAction $joinSession,
        private readonly EndLiveSessionAction $endSession,
        private readonly SendDuelGiftAction $sendDuelGift,
        private readonly DuelScoreService $scoreService,
    ) {}

    /**
     * List live sessions with filters.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'status' => ['nullable', 'string', 'in:waiting,active,paused,completed,cancelled'],
            'host_id' => ['nullable', 'integer', 'exists:users,id'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $query = LiveSession::with(['host.profile', 'guest.profile', 'chatRoom'])
            ->orderByDesc('created_at');

        if (isset($validated['status'])) {
            $query->where('status', LiveSessionStatus::from($validated['status']));
        }

        if (isset($validated['host_id'])) {
            $query->where('host_id', $validated['host_id']);
        }

        $perPage = $validated['per_page'] ?? 20;

        return LiveSessionResource::collection($query->paginate($perPage));
    }

    /**
     * Get active/waiting sessions (lobby).
     */
    public function lobby(): AnonymousResourceCollection
    {
        $sessions = LiveSession::with(['host.profile', 'chatRoom'])
            ->whereIn('status', [LiveSessionStatus::WAITING, LiveSessionStatus::ACTIVE])
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return LiveSessionResource::collection($sessions);
    }

    /**
     * Create a new live session (start a duel).
     */
    public function store(Request $request): JsonResponse
    {
        /** @var User $host */
        $host = $request->user();

        $validated = $request->validate([
            'chat_room_id' => ['nullable', 'integer', 'exists:chat_rooms,id'],
            'meta' => ['nullable', 'array'],
        ]);

        /** @var ChatRoom|null $chatRoom */
        $chatRoom = isset($validated['chat_room_id'])
            ? ChatRoom::find($validated['chat_room_id'])
            : null;

        $session = $this->createSession->execute(
            host: $host,
            chatRoom: $chatRoom,
            meta: $validated['meta'] ?? [],
        );

        $session->load(['host.profile', 'chatRoom']);

        return response()->json([
            'data' => new LiveSessionResource($session),
            'message' => 'Live session created. Waiting for a guest to join.',
        ], 201);
    }

    /**
     * Get a specific live session.
     */
    public function show(string $uuid): JsonResponse
    {
        $session = LiveSession::with(['host.profile', 'guest.profile', 'winner', 'chatRoom'])
            ->where('uuid', $uuid)
            ->firstOrFail();

        // Get real-time scores from Redis if session is active
        $scores = null;
        if ($session->isActive()) {
            $scores = $this->scoreService->getScores($session);
        }

        return response()->json([
            'data' => new LiveSessionResource($session),
            'scores' => $scores,
        ]);
    }

    /**
     * Join a waiting session as guest.
     */
    public function join(Request $request, string $uuid): JsonResponse
    {
        /** @var User $guest */
        $guest = $request->user();

        $session = LiveSession::where('uuid', $uuid)->firstOrFail();

        try {
            $session = $this->joinSession->execute($session, $guest);
            $session->load(['host.profile', 'guest.profile', 'chatRoom']);

            return response()->json([
                'data' => new LiveSessionResource($session),
                'message' => 'Successfully joined the duel. The battle begins!',
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * End or cancel a live session.
     */
    public function end(Request $request, string $uuid): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $session = LiveSession::where('uuid', $uuid)->firstOrFail();

        // Only host can end/cancel the session
        if ($session->host_id !== $user->id) {
            return response()->json([
                'message' => 'Only the host can end this session.',
            ], 403);
        }

        try {
            $session = $this->endSession->execute($session, $user);
            $session->load(['host.profile', 'guest.profile', 'winner', 'chatRoom']);

            $message = $session->status === LiveSessionStatus::CANCELLED
                ? 'Session cancelled.'
                : 'Duel completed!';

            return response()->json([
                'data' => new LiveSessionResource($session),
                'message' => $message,
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get real-time scores for a session.
     */
    public function scores(string $uuid): JsonResponse
    {
        $session = LiveSession::where('uuid', $uuid)->firstOrFail();

        $scores = $this->scoreService->getScores($session);
        $leader = $this->scoreService->getLeader($session);
        $isTied = $this->scoreService->isTied($session);

        return response()->json([
            'data' => [
                'hostScore' => $scores['host'],
                'guestScore' => $scores['guest'],
                'leader' => $leader,
                'isTied' => $isTied,
                'difference' => $this->scoreService->getScoreDifference($session),
            ],
        ]);
    }

    /**
     * Send a gift during a duel.
     */
    public function sendGift(Request $request, string $uuid): JsonResponse
    {
        /** @var User $sender */
        $sender = $request->user();

        $validated = $request->validate([
            'recipient_id' => ['required', 'integer', 'exists:users,id'],
            'gift_slug' => ['required', 'string', 'max:50'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $session = LiveSession::where('uuid', $uuid)->firstOrFail();

        /** @var User $recipient */
        $recipient = User::findOrFail($validated['recipient_id']);
        $quantity = $validated['quantity'] ?? 1;

        try {
            $this->sendDuelGift->execute(
                session: $session,
                sender: $sender,
                recipient: $recipient,
                giftSlug: $validated['gift_slug'],
                quantity: $quantity,
            );

            $scores = $this->scoreService->getScores($session);

            return response()->json([
                'message' => 'Gift sent!',
                'scores' => [
                    'hostScore' => $scores['host'],
                    'guestScore' => $scores['guest'],
                ],
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get events for a session.
     */
    public function events(Request $request, string $uuid): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $session = LiveSession::where('uuid', $uuid)->firstOrFail();

        $events = $session->events()
            ->with(['actor', 'target'])
            ->orderByDesc('created_at')
            ->paginate($validated['per_page'] ?? 50);

        return DuelEventResource::collection($events);
    }
}
