<?php

namespace App\Models;

use App\Enums\EventSpaceType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Space of a sports center rented by the hour for gatherings (grill area, quincho, hall...).
 */
class EventSpace extends Model
{
    protected $fillable = [
        'court_id',
        'name',
        'type',
        'description',
        'price_per_hour',
        'capacity',
        'min_hours',
        'rules',
        'photo_path',
        'opening_time',
        'closing_time',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => EventSpaceType::class,
            'price_per_hour' => 'decimal:2',
            'capacity' => 'integer',
            'min_hours' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function court(): BelongsTo
    {
        return $this->belongsTo(Court::class);
    }

    public function amenities(): BelongsToMany
    {
        return $this->belongsToMany(EventAmenity::class, 'event_space_amenity')->orderBy('name');
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(EventSpaceReservation::class);
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function photoUrl(): ?string
    {
        if (blank($this->photo_path)) {
            return null;
        }

        return Str::startsWith($this->photo_path, ['http://', 'https://'])
            ? $this->photo_path
            : Storage::disk('public')->url($this->photo_path);
    }

    /**
     * Own schedule, or the sports center's when the space has none.
     */
    public function openingTime(): string
    {
        return $this->opening_time ?? $this->court->opening_time;
    }

    public function closingTime(): string
    {
        return $this->closing_time ?? $this->court->closing_time;
    }

    /**
     * Hourly slots for a date, like CourtField::slotsForDate (without sports).
     *
     * @return list<array{start: string, end: string, available: bool, status: string}>
     */
    public function slotsForDate(string $date): array
    {
        $opening = Carbon::parse($date.' '.$this->openingTime());
        $closing = Carbon::parse($date.' '.$this->closingTime());
        $now = now();

        $reservations = $this->reservations()
            ->whereDate('reserved_on', $date)
            ->active()
            ->get();

        $slots = [];
        $cursor = $opening->copy();

        while ($cursor->copy()->addHour()->lessThanOrEqualTo($closing)) {
            $end = $cursor->copy()->addHour();
            $status = match (true) {
                $reservations->contains(fn (EventSpaceReservation $reservation): bool => $reservation->overlaps($cursor, $end)) => 'reserved',
                $cursor->lte($now) => 'past',
                default => 'available',
            };

            $slots[] = [
                'start' => $cursor->format('H:i'),
                'end' => $end->format('H:i'),
                'available' => $status === 'available',
                'status' => $status,
            ];

            $cursor = $end;
        }

        return $slots;
    }
}
