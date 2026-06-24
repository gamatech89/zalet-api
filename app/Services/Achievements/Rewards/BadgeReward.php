<?php

namespace App\Services\Achievements\Rewards;

use App\Models\User;
use App\Enums\RewardType;
use App\Support\JsonMapping\JsonSubType;

#[JsonSubType(RewardType::BADGE)]
class BadgeReward extends Reward
{
    public function __construct(
        public string $badge,
    ) {}

    public function grant(User $user): void
    {
        // TODO: implement badge system
    }
}