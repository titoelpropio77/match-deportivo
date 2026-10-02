<?php

namespace App\Enums;

/**
 * Kind of event space of a sports center, stored in event_spaces.type.
 * Keep in sync with backend/app/Enums/EventSpaceType.php.
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

    public function icon(): string
    {
        return match ($this) {
            self::Grill => 'fas fa-fire',
            self::Quincho => 'fas fa-campground',
            self::Hall => 'fas fa-glass-cheers',
            self::Terrace => 'fas fa-umbrella-beach',
            self::Garden => 'fas fa-tree',
        };
    }
}
