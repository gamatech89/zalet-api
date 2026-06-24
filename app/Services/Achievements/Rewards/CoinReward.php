<?php

namespace App\Services\Achievements\Rewards;

use App\Models\User;
use App\Services\CoinService;
use App\Enums\RewardType;
use App\Support\JsonMapping\JsonSubType;

#[JsonSubType(RewardType::COINS)]
class CoinReward extends Reward
{
    public function __construct(
        public int $amount,
    ) {}

    public function grant(User $user): void
    {
        app(CoinService::class)->deposit($user, $this->amount);
    }
}