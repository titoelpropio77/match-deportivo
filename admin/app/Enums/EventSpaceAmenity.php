<?php

namespace App\Enums;

/**
 * What an event space includes, stored as keys in event_spaces.amenities.
 * Keep in sync with backend/app/Enums/EventSpaceAmenity.php.
 */
enum EventSpaceAmenity: string
{
    case Grill = 'grill';
    case TablesChairs = 'tables_chairs';
    case Restrooms = 'restrooms';
    case Fridge = 'fridge';
    case Kitchen = 'kitchen';
    case Sound = 'sound';
    case AirConditioning = 'air_conditioning';
    case Covered = 'covered';
    case Lighting = 'lighting';
    case Pool = 'pool';
    case KidsArea = 'kids_area';
    case Parking = 'parking';
    case Wifi = 'wifi';

    public function label(): string
    {
        return match ($this) {
            self::Grill => 'Parrilla',
            self::TablesChairs => 'Mesas y sillas',
            self::Restrooms => 'Baños',
            self::Fridge => 'Refrigerador',
            self::Kitchen => 'Cocina',
            self::Sound => 'Equipo de sonido',
            self::AirConditioning => 'Aire acondicionado',
            self::Covered => 'Techado',
            self::Lighting => 'Iluminación',
            self::Pool => 'Piscina',
            self::KidsArea => 'Área de niños',
            self::Parking => 'Parqueo',
            self::Wifi => 'Wi-Fi',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Grill => 'fas fa-fire',
            self::TablesChairs => 'fas fa-chair',
            self::Restrooms => 'fas fa-restroom',
            self::Fridge => 'fas fa-snowflake',
            self::Kitchen => 'fas fa-utensils',
            self::Sound => 'fas fa-music',
            self::AirConditioning => 'fas fa-wind',
            self::Covered => 'fas fa-home',
            self::Lighting => 'fas fa-lightbulb',
            self::Pool => 'fas fa-swimmer',
            self::KidsArea => 'fas fa-child',
            self::Parking => 'fas fa-parking',
            self::Wifi => 'fas fa-wifi',
        };
    }
}
