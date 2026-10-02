<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Sports gear a sports center rents with its courts (ball, racket...), for one sport.
 * Schema and price rules are owned by the backend (App\Models\RentalItem there).
 */
class RentalItem extends Model
{
    public const PRICE_TYPES = [
        'per_hour' => 'Por hora',
        'flat' => 'Por reserva',
    ];

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

    public function priceLabel(): string
    {
        return 'Bs '.number_format((float) $this->price, 2).($this->price_type === 'flat' ? ' por reserva' : ' / hora');
    }

    public function amountFor(int $quantity, int $hours): float
    {
        $units = $this->price_type === 'flat' ? $quantity : $quantity * $hours;

        return round((float) $this->price * $units, 2);
    }

    /**
     * Units taken by reservations that hold their slot and overlap [$start, $end).
     */
    public function reservedQuantity(Carbon $start, Carbon $end): int
    {
        return (int) $this->reservationItems()
            ->with('reservation')
            ->whereHas('reservation', fn (Builder $reservation) => $reservation
                ->whereDate('reserved_on', $start->toDateString())
                ->blocking())
            ->get()
            ->filter(fn (CourtReservationItem $item): bool => $item->reservation->overlaps($start, $end))
            ->sum('quantity');
    }
}
