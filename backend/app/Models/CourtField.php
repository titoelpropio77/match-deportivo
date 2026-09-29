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
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price_per_hour' => 'decimal:2',
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
     * Hourly slots for a date. A slot is unavailable when any reservation
     * on this physical court overlaps it, regardless of the sport.
     *
     * @return list<array{start: string, end: string, available: bool}>
     */
    public function slotsForDate(string $date): array
    {
        $court = $this->court;
        $opening = Carbon::parse($date.' '.$court->opening_time);
        $closing = Carbon::parse($date.' '.$court->closing_time);

        $reservations = $this->reservations()
            ->whereDate('reserved_on', $date)
            ->where('status', '!=', 'cancelled')
            ->get();

        $slots = [];
        $cursor = $opening->copy();

        while ($cursor->copy()->addHour()->lessThanOrEqualTo($closing)) {
            $end = $cursor->copy()->addHour();
            $occupied = $reservations->contains(
                fn (CourtReservation $reservation): bool => $reservation->overlaps($cursor, $end)
            );

            $slots[] = [
                'start' => $cursor->format('H:i'),
                'end' => $end->format('H:i'),
                'available' => ! $occupied,
            ];

            $cursor = $end;
        }

        return $slots;
    }

    /**
     * @param  list<array{start: string, end: string, available: bool}>  $slots
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
