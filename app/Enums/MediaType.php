<?php

namespace App\Enums;

enum MediaType: string
{
    case MOMENT = 'moment';
    case LONG_FORM = 'long_form';
    case EMBED = 'embed';
}
