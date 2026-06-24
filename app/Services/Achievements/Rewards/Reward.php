<?php

namespace App\Services\Achievements\Rewards;

use App\Models\User;
use App\Support\JsonMapping\JsonType;

#[JsonType(field: 'type', subtypes: [
    CoinReward::class,
    BadgeReward::class,
])]
abstract class Reward
{
    abstract public function grant(User $user): void;
}