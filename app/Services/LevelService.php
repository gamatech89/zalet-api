<?php

namespace App\Services;

use App\Domains\Identity\Models\User;
use App\Models\UserLevel;

class LevelService
{
    /**
     * Get or create user level record
     */
    public function getUserLevel(User $user): UserLevel
    {
        return UserLevel::firstOrCreate(
            ['user_id' => $user->id],
            ['xp' => 0, 'level' => 1]
        );
    }

    /**
     * Award XP for watching stream
     */
    public function awardWatchXp(User $user, int $minutes): bool
    {
        if ($minutes <= 0) {
            throw new \InvalidArgumentException('Minutes must be positive');
        }

        $userLevel = $this->getUserLevel($user);
        $xpPerMinute = max(0, (int) config('levels.xp_rewards.watch_stream_per_minute'));
        
        return $userLevel->addXp($minutes * $xpPerMinute);
    }

    /**
     * Award XP for streaming
     */
    public function awardStreamXp(User $user, int $minutes): bool
    {
        if ($minutes <= 0) {
            throw new \InvalidArgumentException('Minutes must be positive');
        }

        $userLevel = $this->getUserLevel($user);
        $xpPerMinute = max(0, (int) config('levels.xp_rewards.stream_per_minute'));
        
        return $userLevel->addXp($minutes * $xpPerMinute);
    }

    /**
     * Award XP for receiving gift
     */
    public function awardReceiveGiftXp(User $user, int $giftValue): bool
    {
        if ($giftValue <= 0) {
            throw new \InvalidArgumentException('Gift value must be positive');
        }

        $userLevel = $this->getUserLevel($user);
        $multiplier = max(0, (float) config('levels.xp_rewards.receive_gift_multiplier'));
        
        return $userLevel->addXp((int) ($giftValue * $multiplier));
    }

    /**
     * Award XP for sending gift
     */
    public function awardSendGiftXp(User $user, int $giftValue): bool
    {
        if ($giftValue <= 0) {
            throw new \InvalidArgumentException('Gift value must be positive');
        }

        $userLevel = $this->getUserLevel($user);
        $multiplier = max(0, (float) config('levels.xp_rewards.send_gift_multiplier'));
        
        return $userLevel->addXp((int) ($giftValue * $multiplier));
    }

    /**
     * Award XP for bar message
     */
    public function awardBarMessageXp(User $user): bool
    {
        $userLevel = $this->getUserLevel($user);
        
        if (!$userLevel->canEarnMessageXp()) {
            return false;
        }

        $userLevel->bar_messages_today++;
        $userLevel->save();

        $xp = max(0, (int) config('levels.xp_rewards.bar_message'));
        return $userLevel->addXp($xp);
    }

    /**
     * Award XP for bar reaction
     */
    public function awardBarReactionXp(User $user): bool
    {
        $userLevel = $this->getUserLevel($user);
        
        if (!$userLevel->canEarnReactionXp()) {
            return false;
        }

        $userLevel->bar_reactions_today++;
        $userLevel->save();

        $xp = max(0, (int) config('levels.xp_rewards.bar_reaction'));
        return $userLevel->addXp($xp);
    }

    /**
     * Award XP for creating bar event
     */
    public function awardCreateEventXp(User $user): bool
    {
        $userLevel = $this->getUserLevel($user);
        $xp = max(0, (int) config('levels.xp_rewards.create_bar_event'));
        
        return $userLevel->addXp($xp);
    }

    /**
     * Award XP for hosting bar stream
     */
    public function awardHostStreamXp(User $user): bool
    {
        $userLevel = $this->getUserLevel($user);
        $xp = max(0, (int) config('levels.xp_rewards.host_bar_stream'));
        
        return $userLevel->addXp($xp);
    }

    /**
     * Get user's full level info with tier
     */
    public function getLevelInfo(User $user): array
    {
        $userLevel = $this->getUserLevel($user);
        
        return [
            'level' => $userLevel->level,
            'xp' => $userLevel->xp,
            'xp_to_next_level' => $userLevel->getXpForNextLevel(),
            'progress_percent' => round($userLevel->getProgressToNextLevel(), 2),
            'tier' => $userLevel->getTier(),
            'bar_perks' => $userLevel->getBarPerks(),
        ];
    }

    /**
     * Check if user can create a bar
     */
    public function canCreateBar(User $user): array
    {
        $userLevel = $this->getUserLevel($user);
        $perks = $userLevel->getBarPerks();
        
        if ($perks['max_bars'] === 0) {
            return [
                'can_create' => false,
                'reason' => 'Level too low. Reach level 5 to create bars.',
                'level_required' => 5,
            ];
        }

        $currentBarsCount = $user->ownedBars()->count();
        
        if ($currentBarsCount >= $perks['max_bars']) {
            return [
                'can_create' => false,
                'reason' => "You've reached your bar limit ({$perks['max_bars']}). Level up to create more!",
                'current_bars' => $currentBarsCount,
                'max_bars' => $perks['max_bars'],
            ];
        }

        return [
            'can_create' => true,
            'current_bars' => $currentBarsCount,
            'max_bars' => $perks['max_bars'],
            'max_members' => $perks['max_members'],
        ];
    }

    /**
     * Get all tier info for display
     */
    public function getAllTiers(): array
    {
        return config('levels.tiers');
    }

    /**
     * Get all bar perks for display
     */
    public function getAllBarPerks(): array
    {
        return config('levels.bar_perks');
    }
}
