<?php

namespace App\Enums;

/**
 * Kind of event space of a sports center, stored in event_spaces.type.
 * Keep in sync with admin/app/Enums/EventSpaceType.php.
 */
enum EventSpaceType: string
{
    case Grill = 'grill';
    case Quincho = 'quincho';
    case Hall = 'hall';
    case Terrace = 'terrace';
    case Garden = 'garden';

    public function label(): string
    {
        return match ($this) {
            self::Grill => 'Parrillero',
            self::Quincho => 'Quincho / churrasquera',
            self::Hall => 'Salón de eventos',
            self::Terrace => 'Terraza',
            self::Garden => 'Área verde',
        };
    }
}
