<?php

namespace App\Policies;

use App\Models\LiveStream;
use App\Models\User;

class LiveStreamPolicy
{
    /**
     * Determine if the user can create streams.
     * Only creators and admins can create streams.
     */
    public function create(User $user): bool
    {
        return $user->isCreator();
    }

    /**
     * Determine if the user can view their stream key.
     * Only the stream owner can see the key.
     */
    public function viewKey(User $user, LiveStream $stream): bool
    {
        return $user->id === $stream->user_id;
    }

    /**
     * Determine if the user can manage (start/stop) the stream.
     */
    public function manage(User $user, LiveStream $stream): bool
    {
        return $user->id === $stream->user_id;
    }

    /**
     * Determine if the user can update the stream.
     */
    public function update(User $user, LiveStream $stream): bool
    {
        return $user->id === $stream->user_id;
    }

    /**
     * Determine if the user can delete the stream.
     */
    public function delete(User $user, LiveStream $stream): bool
    {
        return $user->id === $stream->user_id || $user->isAdmin();
    }
}
