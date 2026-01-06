<?php

declare(strict_types=1);

namespace App\Domains\Duel\Actions;

use App\Domains\Duel\Enums\ChatRoomType;
use App\Domains\Duel\Enums\LiveSessionStatus;
use App\Domains\Duel\Models\ChatRoom;
use App\Domains\Duel\Models\LiveSession;
use App\Domains\Duel\Services\DuelScoreService;
use App\Domains\Identity\Models\User;
use Illuminate\Support\Str;

/**
 * Action to create a new live duel session.
 */
final readonly class CreateLiveSessionAction
{
    public function __construct(
        private DuelScoreService $scoreService
    ) {}

    /**
     * Create a new live session for a duel.
     *
     * @param array<string, mixed> $meta
     */
    public function execute(User $host, ?ChatRoom $chatRoom = null, array $meta = []): LiveSession
    {
        // Create a duel chat room if not provided
        if (! $chatRoom) {
            $chatRoom = $this->createDuelChatRoom($host);
        }

        // Create the live session
        $session = LiveSession::create([
            'uuid' => (string) Str::uuid(),
            'chat_room_id' => $chatRoom->id,
            'host_id' => $host->id,
            'guest_id' => null,
            'status' => LiveSessionStatus::WAITING,
            'host_score' => 0,
            'guest_score' => 0,
            'started_at' => null,
            'ended_at' => null,
            'winner_id' => null,
            'meta' => $meta,
        ]);

        // Initialize score cache
        $this->scoreService->initializeSession($session);

        return $session;
    }

    /**
     * Create a dedicated chat room for the duel.
     */
    private function createDuelChatRoom(User $host): ChatRoom
    {
        $username = $host->profile->username ?? 'User';
        $name = "{$username}'s Duel Arena";

        return ChatRoom::create([
            'uuid' => (string) Str::uuid(),
            'name' => $name,
            'slug' => Str::slug($name) . '-' . Str::random(8),
            'type' => ChatRoomType::DUEL,
            'location_id' => null,
            'max_participants' => 1000,
            'is_active' => true,
            'meta' => [
                'created_for_duel' => true,
                'host_id' => $host->id,
            ],
        ]);
    }
}
