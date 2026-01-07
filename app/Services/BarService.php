<?php

namespace App\Services;

use App\Models\Bar;
use App\Models\BarMember;
use App\Domains\Identity\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Pagination\LengthAwarePaginator;

class BarService
{
    public function __construct(
        private LevelService $levelService
    ) {}

    /**
     * Create a new bar
     */
    public function createBar(User $owner, array $data): Bar
    {
        $canCreate = $this->levelService->canCreateBar($owner);
        
        if (!$canCreate['can_create']) {
            throw new \Exception($canCreate['reason']);
        }

        $bar = Bar::create([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'cover_image_url' => $data['cover_image_url'] ?? null,
            'owner_id' => $owner->id,
            'is_public' => $data['is_public'] ?? true,
            'password' => isset($data['password']) ? Hash::make($data['password']) : null,
            'member_limit' => min($data['member_limit'] ?? 50, $canCreate['max_members']),
        ]);

        // Add owner as member
        BarMember::create([
            'bar_id' => $bar->id,
            'user_id' => $owner->id,
            'role' => 'owner',
        ]);

        return $bar;
    }

    /**
     * Update bar
     */
    public function updateBar(Bar $bar, array $data): Bar
    {
        $updateData = [];

        if (isset($data['name'])) {
            $updateData['name'] = $data['name'];
        }
        if (isset($data['description'])) {
            $updateData['description'] = $data['description'];
        }
        if (isset($data['cover_image_url'])) {
            $updateData['cover_image_url'] = $data['cover_image_url'];
        }
        if (isset($data['is_public'])) {
            $updateData['is_public'] = $data['is_public'];
        }
        if (isset($data['password'])) {
            $updateData['password'] = $data['password'] ? Hash::make($data['password']) : null;
        }
        if (isset($data['member_limit'])) {
            // Check owner's max allowed
            $ownerPerks = $this->levelService->getUserLevel($bar->owner)->getBarPerks();
            $updateData['member_limit'] = min($data['member_limit'], $ownerPerks['max_members']);
        }

        $bar->update($updateData);
        return $bar->fresh();
    }

    /**
     * Join a bar
     */
    public function joinBar(User $user, Bar $bar, ?string $password = null): BarMember
    {
        if ($bar->hasMember($user)) {
            throw new \Exception('Already a member of this bar.');
        }

        if ($bar->isFull()) {
            throw new \Exception('Bar is full.');
        }

        if (!$bar->is_public) {
            if ($bar->password && !Hash::check($password ?? '', $bar->password)) {
                throw new \Exception('Invalid password.');
            }
        }

        $member = BarMember::create([
            'bar_id' => $bar->id,
            'user_id' => $user->id,
            'role' => 'member',
        ]);

        $bar->incrementMemberCount();

        return $member;
    }

    /**
     * Leave a bar
     */
    public function leaveBar(User $user, Bar $bar): bool
    {
        $member = $bar->members()->where('user_id', $user->id)->first();

        if (!$member) {
            throw new \Exception('Not a member of this bar.');
        }

        if ($member->role === 'owner') {
            throw new \Exception('Owner cannot leave. Transfer ownership or delete the bar.');
        }

        $member->delete();
        $bar->decrementMemberCount();

        return true;
    }

    /**
     * Kick a member from bar
     */
    public function kickMember(User $moderator, Bar $bar, User $target): bool
    {
        if (!$bar->isModeratorOrOwner($moderator)) {
            throw new \Exception('Not authorized to kick members.');
        }

        $targetMember = $bar->members()->where('user_id', $target->id)->first();

        if (!$targetMember) {
            throw new \Exception('User is not a member.');
        }

        if ($targetMember->role === 'owner') {
            throw new \Exception('Cannot kick the owner.');
        }

        if ($targetMember->role === 'moderator' && !$bar->members()->where('user_id', $moderator->id)->where('role', 'owner')->exists()) {
            throw new \Exception('Only owner can kick moderators.');
        }

        $targetMember->delete();
        $bar->decrementMemberCount();

        return true;
    }

    /**
     * Promote member to moderator
     */
    public function promoteMember(User $owner, Bar $bar, User $target): BarMember
    {
        $ownerMember = $bar->members()->where('user_id', $owner->id)->where('role', 'owner')->first();
        
        if (!$ownerMember) {
            throw new \Exception('Only bar owner can promote members.');
        }

        $targetMember = $bar->members()->where('user_id', $target->id)->first();

        if (!$targetMember) {
            throw new \Exception('User is not a member.');
        }

        $targetMember->promoteToModerator();
        return $targetMember;
    }

    /**
     * Demote moderator to member
     */
    public function demoteMember(User $owner, Bar $bar, User $target): BarMember
    {
        $ownerMember = $bar->members()->where('user_id', $owner->id)->where('role', 'owner')->first();
        
        if (!$ownerMember) {
            throw new \Exception('Only bar owner can demote moderators.');
        }

        $targetMember = $bar->members()->where('user_id', $target->id)->first();

        if (!$targetMember) {
            throw new \Exception('User is not a member.');
        }

        $targetMember->demoteToMember();
        return $targetMember;
    }

    /**
     * Mute a member
     */
    public function muteMember(User $moderator, Bar $bar, User $target, int $minutes): BarMember
    {
        if (!$bar->isModeratorOrOwner($moderator)) {
            throw new \Exception('Not authorized to mute members.');
        }

        $targetMember = $bar->members()->where('user_id', $target->id)->first();

        if (!$targetMember) {
            throw new \Exception('User is not a member.');
        }

        if ($targetMember->canModerate()) {
            throw new \Exception('Cannot mute moderators or owner.');
        }

        $targetMember->mute($minutes);
        return $targetMember;
    }

    /**
     * Get public bars
     */
    public function getPublicBars(int $perPage = 20): LengthAwarePaginator
    {
        return Bar::where('is_public', true)
            ->where('is_active', true)
            ->with(['owner.profile'])
            ->withCount('members')
            ->orderByDesc('member_count')
            ->paginate($perPage);
    }

    /**
     * Get user's bars (member of)
     */
    public function getUserBars(User $user): \Illuminate\Database\Eloquent\Collection
    {
        return Bar::whereHas('members', function ($query) use ($user) {
            $query->where('user_id', $user->id);
        })
            ->with(['owner.profile'])
            ->withCount('members')
            ->get();
    }

    /**
     * Get user's owned bars
     */
    public function getOwnedBars(User $user): \Illuminate\Database\Eloquent\Collection
    {
        return Bar::where('owner_id', $user->id)
            ->withCount('members')
            ->get();
    }

    /**
     * Search bars
     */
    public function searchBars(string $query, int $perPage = 20): LengthAwarePaginator
    {
        return Bar::where('is_public', true)
            ->where('is_active', true)
            ->where(function ($q) use ($query) {
                $q->where('name', 'ilike', "%{$query}%")
                    ->orWhere('description', 'ilike', "%{$query}%");
            })
            ->with(['owner.profile'])
            ->withCount('members')
            ->orderByDesc('member_count')
            ->paginate($perPage);
    }

    /**
     * Delete a bar
     */
    public function deleteBar(User $user, Bar $bar): bool
    {
        if ($bar->owner_id !== $user->id) {
            throw new \Exception('Only owner can delete the bar.');
        }

        $bar->delete();
        return true;
    }
}
