<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Sports gear a sports center rents with its courts (ball, racket...), for one sport.
 * Keep the price rules in sync with admin/app/Models/RentalItem.php.
 */
class RentalItem extends Model
{
    public const PRICE_PER_HOUR = 'per_hour';

    public const PRICE_FLAT = 'flat';

    protected $fillable = [
        'court_id',
        'sport_id',
        'name',
        'description',
        'price',
        'price_type',
        'stock',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'stock' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function court(): BelongsTo
    {
        return $this->belongsTo(Court::class);
    }

    public function sport(): BelongsTo
    {
        return $this->belongsTo(Sport::class);
    }

    public function reservationItems(): HasMany
    {
        return $this->hasMany(CourtReservationItem::class);
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Price of [quantity] units for a reservation of [hours].
     */
    public function amountFor(int $quantity, int $hours): float
    {
        $units = $this->price_type === self::PRICE_FLAT ? $quantity : $quantity * $hours;

        return round((float) $this->price * $units, 2);
    }

    /**
     * Units already rented by active reservations overlapping [$start, $end).
     */
    public function reservedQuantity(Carbon $start, Carbon $end): int
    {
        return (int) $this->reservationItems()
            ->with('reservation')
            ->whereHas('reservation', fn (Builder $reservation) => $reservation
                ->whereDate('reserved_on', $start->toDateString())
                ->active())
            ->get()
            ->filter(fn (CourtReservationItem $item): bool => $item->reservation->overlaps($start, $end))
            ->sum('quantity');
    }
}
