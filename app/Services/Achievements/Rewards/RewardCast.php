<?php

namespace App\Services\Achievements\Rewards;

use App\Support\JsonMapping\JsonTypeCast;

class RewardCast extends JsonTypeCast
{
    public function __construct()
    {
        parent::__construct(Reward::class);
    }
}
