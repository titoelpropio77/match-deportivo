<?php

namespace App\Enums;

enum MatchPlayerStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Reserved = 'reserved';
}
