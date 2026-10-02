<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Gear rented with a court reservation; name and price are copied so the history stays when the item changes.
 */
class CourtReservationItem extends Model
{
    protected $fillable = [
        'court_reservation_id',
        'rental_item_id',
        'name',
        'quantity',
        'unit_price',
        'price_type',
        'amount',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'amount' => 'decimal:2',
        ];
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(CourtReservation::class, 'court_reservation_id');
    }

    public function rentalItem(): BelongsTo
    {
        return $this->belongsTo(RentalItem::class);
    }
}
