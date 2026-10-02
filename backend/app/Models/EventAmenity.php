<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * What an event space can include (parrilla, baños...), managed from the admin panel.
 */
class EventAmenity extends Model
{
    protected $fillable = [
        'key',
        'name',
        'icon',
    ];

    public function spaces(): BelongsToMany
    {
        return $this->belongsToMany(EventSpace::class, 'event_space_amenity');
    }
}
