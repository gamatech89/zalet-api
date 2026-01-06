<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domains\Duel\Actions\CreatePublicRoomAction;
use App\Domains\Duel\Actions\SendMessageAction;
use App\Domains\Duel\Enums\ChatRoomType;
use App\Domains\Duel\Models\ChatRoom;
use App\Domains\Duel\Resources\ChatRoomResource;
use App\Domains\Duel\Resources\MessageResource;
use App\Domains\Identity\Models\Location;
use App\Domains\Identity\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;

final class ChatRoomController extends Controller
{
    public function __construct(
        private readonly SendMessageAction $sendMessage,
        private readonly CreatePublicRoomAction $createPublicRoom,
    ) {}

    /**
     * List active chat rooms (public kafanas).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'type' => ['nullable', 'string', 'in:public_kafana,private,duel'],
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $query = ChatRoom::with(['location', 'creator.profile'])
            ->where('is_active', true)
            ->orderByDesc('created_at');

        if (isset($validated['type'])) {
            $query->where('type', ChatRoomType::from($validated['type']));
        } else {
            // Default to public kafanas only (exclude DMs and duels)
            $query->where('type', ChatRoomType::PUBLIC_KAFANA);
        }

        if (isset($validated['location_id'])) {
            $query->where('location_id', $validated['location_id']);
        }

        $perPage = $validated['per_page'] ?? 20;

        return ChatRoomResource::collection($query->paginate($perPage));
    }

    /**
     * Create a new chat room (kafana).
     * Only admins, moderators, and creators can create public rooms.
     */
    public function store(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'type' => ['nullable', 'string', 'in:public_kafana,private'],
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],
            'max_participants' => ['nullable', 'integer', 'min:2', 'max:1000'],
        ]);

        $type = ChatRoomType::from($validated['type'] ?? 'public_kafana');

        // Check permissions for public rooms
        if ($type === ChatRoomType::PUBLIC_KAFANA && ! $user->canCreatePublicRooms()) {
            return response()->json([
                'message' => 'You do not have permission to create public chat rooms. Only creators, moderators, and admins can create public rooms.',
            ], 403);
        }

        /** @var Location|null $location */
        $location = isset($validated['location_id'])
            ? Location::find($validated['location_id'])
            : null;

        try {
            $room = $this->createPublicRoom->execute(
                creator: $user,
                name: $validated['name'],
                description: $validated['description'] ?? null,
                location: $location,
                maxParticipants: $validated['max_participants'] ?? 500,
            );
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        }

        $room->load(['location', 'creator.profile']);

        return response()->json([
            'data' => new ChatRoomResource($room),
            'message' => 'Chat room created successfully.',
        ], 201);
    }

    /**
     * Get a specific chat room.
     */
    public function show(string $uuid): JsonResponse
    {
        $room = ChatRoom::with(['location', 'creator.profile', 'liveSession.host.profile', 'liveSession.guest.profile'])
            ->where('uuid', $uuid)
            ->firstOrFail();

        return response()->json([
            'data' => new ChatRoomResource($room),
        ]);
    }

    /**
     * Get messages in a chat room.
     */
    public function messages(Request $request, string $uuid): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'before' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $room = ChatRoom::where('uuid', $uuid)->firstOrFail();

        $query = $room->messages()
            ->with(['user.profile'])
            ->orderByDesc('created_at');

        if (isset($validated['before'])) {
            $query->where('created_at', '<', $validated['before']);
        }

        $perPage = $validated['per_page'] ?? 50;

        return MessageResource::collection($query->paginate($perPage));
    }

    /**
     * Send a message to a chat room.
     */
    public function sendMessage(Request $request, string $uuid): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'content' => ['required', 'string', 'min:1', 'max:1000'],
            'meta' => ['nullable', 'array'],
        ]);

        $room = ChatRoom::where('uuid', $uuid)->firstOrFail();

        try {
            $message = $this->sendMessage->execute(
                room: $room,
                user: $user,
                content: $validated['content'],
                meta: $validated['meta'] ?? [],
            );

            $message->load('user.profile');

            return response()->json([
                'data' => new MessageResource($message),
            ], 201);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Deactivate a chat room.
     */
    public function destroy(Request $request, string $uuid): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $room = ChatRoom::where('uuid', $uuid)->firstOrFail();

        // Only creator can deactivate
        $creatorId = $room->meta['created_by'] ?? null;
        if ($creatorId !== $user->id) {
            return response()->json([
                'message' => 'Only the room creator can deactivate this room.',
            ], 403);
        }

        $room->update(['is_active' => false]);

        return response()->json([
            'message' => 'Chat room deactivated.',
        ]);
    }
}
