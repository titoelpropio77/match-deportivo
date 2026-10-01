<?php

namespace App\Models;

use App\Enums\CourtFieldFeature;
use Illuminate\Database\Eloquent\Casts\AsEnumCollection;
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

    /**
     * Hourly slots for a date. A slot is unavailable when any active reservation
     * on this physical court overlaps it, regardless of the sport, or when it already started.
     * Reserved slots carry the sport they were booked for.
     *
     * @return list<array{start: string, end: string, available: bool, status: string, sport: array{id: int, name: string}|null}>
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
