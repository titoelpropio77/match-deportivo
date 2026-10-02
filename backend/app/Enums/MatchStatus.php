<?php

namespace App\Enums;

enum MatchStatus: string
{
    case Open = 'open';
    case Full = 'full';
    case Cancelled = 'cancelled';
    case Finished = 'finished';
}
