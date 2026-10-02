<?php

namespace App\Models;

use App\Enums\EventKind;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Hour range of an event space. Statuses and the payment window follow CourtReservation;
 * keep them in sync with admin/app/Models/EventSpaceReservation.php.
 */
class EventSpaceReservation extends Model
{
    public const STATUS_PENDING_PAYMENT = 'pending_payment';

    public const STATUS_PAID = 'paid';

    /**
     * Registered by venue staff, to be paid at the venue. Blocks the hours without expiring.
     */
    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_CANCELLED = 'cancelled';

    /**
     * Minutes an unpaid reservation holds its hours before they are released.
     */
    public const PAYMENT_WINDOW_MINUTES = 15;

    protected $fillable = [
        'code',
        'event_space_id',
        'user_id',
        'customer_name',
        'customer_phone',
        'reserved_on',
        'starts_at',
        'ends_at',
        'hours',
        'guests',
        'event_type',
        'amount',
        'status',
        'source',
        'payment_method',
        'paid_at',
        'cancelled_at',
        'cancelled_by',
        'cancellation_reason',
        'refunded_at',
        'created_by',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'reserved_on' => 'date',
            'hours' => 'integer',
            'guests' => 'integer',
            'event_type' => EventKind::class,
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'refunded_at' => 'datetime',
        ];
    }

    public function space(): BelongsTo
    {
        return $this->belongsTo(EventSpace::class, 'event_space_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Reservations that block their hours: paid or venue-confirmed ones, and unpaid ones still inside the payment window.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where(function (Builder $query): void {
            $query->whereIn('status', [self::STATUS_PAID, self::STATUS_CONFIRMED])
                ->orWhere(function (Builder $query): void {
                    $query->where('status', self::STATUS_PENDING_PAYMENT)
                        ->where('created_at', '>=', now()->subMinutes(self::PAYMENT_WINDOW_MINUTES));
                });
        });
    }

    public function cancelledByVenue(): bool
    {
        return $this->status === self::STATUS_CANCELLED
            && $this->cancelled_by !== null
            && $this->cancelled_by !== $this->user_id;
    }

    public function paymentExpired(): bool
    {
        return $this->status === self::STATUS_PENDING_PAYMENT
            && $this->created_at->lt(now()->subMinutes(self::PAYMENT_WINDOW_MINUTES));
    }

    public function overlaps(Carbon $start, Carbon $end): bool
    {
        $date = $start->toDateString();
        $reservedStart = Carbon::parse($date.' '.$this->starts_at);
        $reservedEnd = Carbon::parse($date.' '.$this->ends_at);

        return $reservedStart->lt($end) && $reservedEnd->gt($start);
    }

    public function endsAt(): Carbon
    {
        return Carbon::parse($this->reserved_on->toDateString().' '.$this->ends_at);
    }
}
