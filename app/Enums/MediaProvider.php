<?php

namespace App\Enums;

enum MediaProvider: string
{
    case NATIVE = 'native';
    case YOUTUBE = 'youtube';
    case VIMEO = 'vimeo';
    case DAILYMOTION = 'dailymotion';
}
