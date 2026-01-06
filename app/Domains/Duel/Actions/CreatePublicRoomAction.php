<?php

declare(strict_types=1);

namespace App\Domains\Duel\Actions;

use App\Domains\Duel\Enums\ChatRoomType;
use App\Domains\Duel\Models\ChatRoom;
use App\Domains\Identity\Models\Location;
use App\Domains\Identity\Models\User;
use App\Domains\Shared\Enums\UserRole;
use Illuminate\Support\Str;

/**
 * Create a new public chat room (kafana).
 * Only users with appropriate permissions can create public rooms.
 */
final class CreatePublicRoomAction
{
    /**
     * Create a new public kafana room.
     *
     * @throws \DomainException if user lacks permission
     */
    public function execute(
        User $creator,
        string $name,
        ?string $description = null,
        ?Location $location = null,
        int $maxParticipants = 500,
    ): ChatRoom {
        $this->validatePermission($creator);

        return ChatRoom::create([
            'uuid' => (string) Str::uuid(),
            'name' => $name,
            'description' => $description,
            'type' => ChatRoomType::PUBLIC_KAFANA,
            'location_id' => $location?->id,
            'creator_id' => $creator->id,
            'max_participants' => $maxParticipants,
            'is_active' => true,
            'meta' => [],
        ]);
    }

    /**
     * Validate user has permission to create public rooms.
     *
     * @throws \DomainException
     */
    private function validatePermission(User $user): void
    {
        if (! $user->role->canCreatePublicRooms()) {
            throw new \DomainException(
                'Only admins, moderators, and creators can create public chat rooms.'
            );
        }
    }
}
