<?php

namespace App\Enums;

enum TimeWindowUnit: string
{
    case Minutes = 'minutes';
    case Hours   = 'hours';
    case Days    = 'days';
    case Weeks   = 'weeks';
    case Months  = 'months';
}