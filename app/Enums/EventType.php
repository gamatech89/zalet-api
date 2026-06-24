<?php

namespace App\Enums;

enum EventType: string
{
    case MESSAGE_SENT = 'message_sent';
    case GIFT_SENT = 'gift_sent';
    case USER_FOLLOWED = 'user_followed';
    case FOLLOWER_GAINED = 'follower_gained';
    case STREAM_STARTED = 'stream_started';
    case DAILY_LOGIN = 'daily_login';
    case MEDIA_POSTED = 'media_posted';
}
