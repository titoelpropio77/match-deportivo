<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

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
        'air_conditioning_price',
        'lighting_price',
        'lighting_from',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price_per_hour' => 'decimal:2',
            'air_conditioning_price' => 'decimal:2',
            'lighting_price' => 'decimal:2',
        ];
    }

    /**
     * Same pricing rules as the backend CourtField: keep both in sync.
     */
    public function offersAirConditioning(): bool
    {
        return $this->hasFeature(CourtFeature::AIR_CONDITIONING)
            && (float) $this->air_conditioning_price > 0;
    }

    public function chargesLighting(): bool
    {
        return $this->hasFeature(CourtFeature::LIGHTING)
            && (float) $this->lighting_price > 0
            && $this->lighting_from !== null;
    }

    /**
     * Any part of the hour starting at $start falls after lighting_from.
     */
    public function isLitHour(Carbon $start): bool
    {
        return $this->chargesLighting()
            && $start->copy()->addHour()->gt(Carbon::parse($start->toDateString().' '.$this->lighting_from));
    }

    /**
     * @return array{air_conditioning: bool, air_conditioning_amount: float, lighting_amount: float}
     */
    public function surchargesFor(Carbon $start, Carbon $end, bool $airConditioning): array
    {
        $hours = (int) $start->diffInHours($end);
        $litHours = 0;
        for ($hour = $start->copy(); $hour->lt($end); $hour->addHour()) {
            $litHours += $this->isLitHour($hour) ? 1 : 0;
        }
        $airConditioning = $airConditioning && $this->offersAirConditioning();

        return [
            'air_conditioning' => $airConditioning,
            'air_conditioning_amount' => $airConditioning ? round((float) $this->air_conditioning_price * $hours, 2) : 0.0,
            'lighting_amount' => round((float) $this->lighting_price * $litHours, 2),
        ];
    }

    public function hasFeature(string $key): bool
    {
        return $this->features->contains('key', $key);
    }

    public function court(): BelongsTo
    {
        return $this->belongsTo(Court::class);
    }

    public function sports(): BelongsToMany
    {
        return $this->belongsToMany(Sport::class, 'court_field_sport');
    }

    public function features(): BelongsToMany
    {
        return $this->belongsToMany(CourtFeature::class, 'court_field_feature')->orderBy('name');
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(CourtReservation::class);
    }
}
