<?php

namespace App\Enums;

enum PropertyStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Sold = 'sold';
}
