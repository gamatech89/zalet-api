<?php

namespace App\Enums;

enum EventType: string
{
    case Deposit      = 'deposit';
    case Login        = 'login';
    case Referral     = 'referral';
    case Purchase     = 'purchase';
    case ProfileSetup = 'profile_setup';
}