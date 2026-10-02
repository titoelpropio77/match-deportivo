<?php

namespace App\Enums;

/**
 * What the customer is celebrating, stored in event_space_reservations.event_type.
 * Keep in sync with backend/app/Enums/EventKind.php.
 */
enum EventKind: string
{
    case Barbecue = 'barbecue';
    case Birthday = 'birthday';
    case Meeting = 'meeting';
    case Celebration = 'celebration';
    case Corporate = 'corporate';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Barbecue => 'Asado / parrillada',
            self::Birthday => 'Cumpleaños',
            self::Meeting => 'Reunión',
            self::Celebration => 'Fiesta',
            self::Corporate => 'Evento de empresa',
            self::Other => 'Otro',
        };
    }
}
