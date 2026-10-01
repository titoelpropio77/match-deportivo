<?php

namespace App\Enums;

/**
 * Amenities of a physical court, stored as keys in court_fields.features.
 * Keep in sync with admin/app/Enums/CourtFieldFeature.php.
 */
enum CourtFieldFeature: string
{
    case AirConditioning = 'air_conditioning';
    case Covered = 'covered';
    case Lighting = 'lighting';
    case Stands = 'stands';

    public function label(): string
    {
        return match ($this) {
            self::AirConditioning => 'Aire acondicionado',
            self::Covered => 'Techada',
            self::Lighting => 'Iluminación nocturna',
            self::Stands => 'Graderías',
        };
    }
}
