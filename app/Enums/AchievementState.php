<?php

namespace App\Enums;

enum AchievementState: string
{
    case CLAIMABLE = 'claimable';
    case IN_PROGRESS = 'in_progress';
    case COMPLETED = 'completed';
}
