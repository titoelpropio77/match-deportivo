<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

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
     * Air conditioning is an option the player picks (and pays per hour) only when the court has it and a price is set.
     */
    public function offersAirConditioning(): bool
    {
        return $this->hasFeature(CourtFeature::AIR_CONDITIONING)
            && (float) $this->air_conditioning_price > 0;
    }

    /**
     * Night lighting is charged per hour from lighting_from when the court has it and a price is set.
     */
    public function chargesLighting(): bool
    {
        return $this->hasFeature(CourtFeature::LIGHTING)
            && (float) $this->lighting_price > 0
            && $this->lighting_from !== null;
    }

    /**
     * Whether the hour starting at $start is played with the lights on (and charged for them):
     * any part of it falls after lighting_from.
     */
    public function isLitHour(Carbon $start): bool
    {
        if (! $this->chargesLighting()) {
            return false;
        }

        return $start->copy()->addHour()->gt(Carbon::parse($start->toDateString().' '.$this->lighting_from));
    }

    /**
     * Extras of a booked range: air conditioning when chosen, plus lighting for every hour from lighting_from.
     *
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

    /**
     * Hourly slots for a date. A slot is unavailable when any active reservation
     * on this physical court overlaps it, regardless of the sport, or when it already started.
     * Reserved slots carry the sport they were booked for.
     *
     * @return list<array{start: string, end: string, available: bool, status: string, lighting: bool, sport: array{id: int, name: string}|null}>
     */
    public function slotsForDate(string $date): array
    {
        $court = $this->court;
        $opening = Carbon::parse($date.' '.$court->opening_time);
        $closing = Carbon::parse($date.' '.$court->closing_time);
        $now = now();

        $reservations = $this->reservations()
            ->with('sport')
            ->whereDate('reserved_on', $date)
            ->active()
            ->get();

        $slots = [];
        $cursor = $opening->copy();

        while ($cursor->copy()->addHour()->lessThanOrEqualTo($closing)) {
            $end = $cursor->copy()->addHour();
            $reservation = $reservations->first(
                fn (CourtReservation $reservation): bool => $reservation->overlaps($cursor, $end)
            );
            $status = match (true) {
                $reservation !== null => 'reserved',
                $cursor->lte($now) => 'past',
                default => 'available',
            };

            $slots[] = [
                'start' => $cursor->format('H:i'),
                'end' => $end->format('H:i'),
                'available' => $status === 'available',
                'status' => $status,
                // Night hour: the lighting extra is added to its price.
                'lighting' => $this->isLitHour($cursor),
                'sport' => $reservation?->sport
                    ? ['id' => $reservation->sport->id, 'name' => $reservation->sport->name]
                    : null,
            ];

            $cursor = $end;
        }

        return $slots;
    }

    /**
     * @param  list<array{start: string, end: string, available: bool, status: string}>  $slots
     * @return list<array{start: string, end: string}>
     */
    public static function freeRanges(array $slots): array
    {
        $ranges = [];
        $current = null;

        foreach ($slots as $slot) {
            if (! $slot['available']) {
                if ($current !== null) {
                    $ranges[] = $current;
                    $current = null;
                }

                continue;
            }

            if ($current === null) {
                $current = ['start' => $slot['start'], 'end' => $slot['end']];

                continue;
            }

            $current['end'] = $slot['end'];
        }

        if ($current !== null) {
            $ranges[] = $current;
        }

        return $ranges;
    }
}
