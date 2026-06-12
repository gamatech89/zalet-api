<?php

namespace App\Enums;

enum AggregatorType: string
{
    case Count  = 'count';
    case Sum    = 'sum';
    case Max    = 'max';
    case Streak = 'streak';
}