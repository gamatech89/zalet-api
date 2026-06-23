<?php

namespace App\Enums;

enum FilterableEntity: string
{
    case GIFTS = 'gifts';
    case USERS = 'users';
    case CONVERSATIONS = 'conversations';
}
