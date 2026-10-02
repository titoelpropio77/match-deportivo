<?php

namespace App\Models;

use App\Enums\EventKind;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Hour range of an event space. Schema and booking rules are owned by the API backend
 * (App\Models\EventSpaceReservation there); statuses work like CourtReservation's.
 */
class EventSpaceReservation extends Model
{
    public const STATUS_PENDING_PAYMENT = 'pending_payment';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_PAID = 'paid';

    public const STATUS_CANCELLED = 'cancelled';

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

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Reservations of the venues the user can see (partner: owned, manager: assigned).
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if (! $user->can('courts.view_all')) {
            $query->whereHas('space.court', fn (Builder $court) => $court->visibleTo($user));
        }
    }

    /**
     * Reservations that occupy their hours (same rule as the backend's `active` scope).
     */
    public function scopeBlocking(Builder $query): void
    {
        $query->where(fn (Builder $query) => $query
            ->whereIn('status', [self::STATUS_PAID, self::STATUS_CONFIRMED])
            ->orWhere(fn (Builder $pending) => $pending
                ->where('status', self::STATUS_PENDING_PAYMENT)
                ->where('created_at', '>=', now()->subMinutes(self::PAYMENT_WINDOW_MINUTES))));
    }

    public function scopeCollected(Builder $query): void
    {
        $query->whereNotNull('paid_at')->whereNull('refunded_at');
    }

    public function displayStatus(): string
    {
        return match (true) {
            $this->status === self::STATUS_CANCELLED && $this->refunded_at !== null => 'refunded',
            $this->status === self::STATUS_CANCELLED && $this->paid_at !== null => 'refund_pending',
            $this->isExpired() => 'expired',
            default => $this->status,
        };
    }

    public function statusLabel(): string
    {
        return CourtReservation::DISPLAY_STATUSES[$this->displayStatus()][0] ?? $this->status;
    }

    public function statusColor(): string
    {
        return CourtReservation::DISPLAY_STATUSES[$this->displayStatus()][1] ?? 'secondary';
    }

    public function isExpired(): bool
    {
        return $this->status === self::STATUS_PENDING_PAYMENT
            && $this->created_at?->lt(now()->subMinutes(self::PAYMENT_WINDOW_MINUTES));
    }

    public function startsAt(): Carbon
    {
        return Carbon::parse($this->reserved_on->toDateString().' '.$this->starts_at);
    }

    public function endsAt(): Carbon
    {
        return Carbon::parse($this->reserved_on->toDateString().' '.$this->ends_at);
    }

    public function canBeCancelled(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING_PAYMENT, self::STATUS_CONFIRMED, self::STATUS_PAID], true)
            && ! $this->isExpired()
            && $this->endsAt()->gt(now());
    }

    public function canRegisterPayment(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING_PAYMENT, self::STATUS_CONFIRMED], true)
            && ! $this->isExpired();
    }

    public function canBeRefunded(): bool
    {
        return $this->status === self::STATUS_CANCELLED && $this->paid_at !== null && $this->refunded_at === null;
    }

    public function customerName(): string
    {
        return $this->user?->name ?? $this->customer_name ?? '—';
    }

    public function customerPhone(): ?string
    {
        return $this->user?->phone ?? $this->customer_phone;
    }

    public function timeRange(): string
    {
        return substr((string) $this->starts_at, 0, 5).' – '.substr((string) $this->ends_at, 0, 5);
    }

    public function overlaps(Carbon $start, Carbon $end): bool
    {
        return $this->startsAt()->lt($end) && $this->endsAt()->gt($start);
    }
}
