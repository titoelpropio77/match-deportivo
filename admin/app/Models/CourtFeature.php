<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Amenity of a physical court (aire acondicionado, techada...), managed from the admin panel.
 */
class CourtFeature extends Model
{
    /**
     * Keys the surcharges depend on (CourtField::offersAirConditioning / chargesLighting):
     * they cannot be renamed or deleted.
     */
    public const AIR_CONDITIONING = 'air_conditioning';

    public const LIGHTING = 'lighting';

    public const SYSTEM_KEYS = [self::AIR_CONDITIONING, self::LIGHTING];

    protected $fillable = [
        'key',
        'name',
        'icon',
    ];

    public function isSystem(): bool
    {
        return in_array($this->key, self::SYSTEM_KEYS, true);
    }

    public function fields(): BelongsToMany
    {
        return $this->belongsToMany(CourtField::class, 'court_field_feature');
    }
}
