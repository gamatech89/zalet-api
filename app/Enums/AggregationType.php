<?php

namespace App\Enums;

enum AggregationType: string
{
    case COUNT = 'count';
    case TOTAL = 'total';
    case UNIQUE_COUNT = 'unique_count';
    case SEQUENCE = 'sequence';
}
