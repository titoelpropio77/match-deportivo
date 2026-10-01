<?php

namespace App\Models;

use App\Enums\CourtFieldFeature;
use Illuminate\Database\Eloquent\Casts\AsEnumCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Physical bookable court inside a venue.
 */
class CourtField extends Model
{
    protected $fillable = [
        'court_id',
        'name',
        'price_per_hour',
        'dimensions',
        'description',
        'features',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price_per_hour' => 'decimal:2',
            'features' => AsEnumCollection::of(CourtFieldFeature::class),
        ];
    }

    public function court(): BelongsTo
    {
        return $this->belongsTo(Court::class);
    }

    public function sports(): BelongsToMany
    {
        return $this->belongsToMany(Sport::class, 'court_field_sport');
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(CourtReservation::class);
    }
}
