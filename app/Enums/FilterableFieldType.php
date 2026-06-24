<?php

namespace App\Enums;

enum FilterableFieldType: string
{
    case ENTITY = 'entity';
    case NUMBER = 'number';
    case STRING = 'string';
}
