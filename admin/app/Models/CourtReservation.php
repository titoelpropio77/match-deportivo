<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Hourly booking of a physical court. Schema and booking rules are owned by the API backend
 * (App\Models\CourtReservation there); keep the statuses and the blocking rule in sync.
 */
class CourtReservation extends Model
{
    public const STATUS_PENDING_PAYMENT = 'pending_payment';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_PAID = 'paid';

    public const STATUS_CANCELLED = 'cancelled';

    /**
     * Minutes an unpaid app reservation holds its slot (same as the backend).
     */
    public const PAYMENT_WINDOW_MINUTES = 15;

    public const PAYMENT_METHODS = [
        'qr' => 'QR',
        'cash' => 'Efectivo',
        'transfer' => 'Transferencia',
    ];

    public const SOURCES = [
        'app' => 'App',
        'admin' => 'Panel (presencial / teléfono)',
    ];

    /**
     * Status shown in the panel => [label, badge color]. `expired` and `refunded` are derived.
     */
    public const DISPLAY_STATUSES = [
        'pending_payment' => ['Pago pendiente', 'warning'],
        'confirmed' => ['Confirmada (paga en el local)', 'info'],
        'paid' => ['Pagada', 'success'],
        'cancelled' => ['Anulada', 'danger'],
        'refund_pending' => ['Anulada · devolución pendiente', 'danger'],
        'refunded' => ['Anulada · reembolsada', 'secondary'],
        'expired' => ['Expirada (sin pago)', 'secondary'],
    ];

    protected $fillable = [
        'booking_code',
        'court_field_id',
        'user_id',
        'customer_name',
        'customer_phone',
        'sport_id',
        'reserved_on',
        'starts_at',
        'ends_at',
        'hours',
        'amount',
        'items_amount',
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
            'amount' => 'decimal:2',
            'items_amount' => 'decimal:2',
            'paid_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'refunded_at' => 'datetime',
        ];
    }

    public function field(): BelongsTo
    {
        return $this->belongsTo(CourtField::class, 'court_field_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sport(): BelongsTo
    {
        return $this->belongsTo(Sport::class);
    }

    /**
     * Gear rented with this reservation; `amount` already includes `items_amount`.
     */
    public function items(): HasMany
    {
        return $this->hasMany(CourtReservationItem::class);
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
            $query->whereHas('field.court', fn (Builder $court) => $court->visibleTo($user));
        }
    }

    /**
     * Reservations that occupy their slot (same rule as the backend's `active` scope).
     */
    public function scopeBlocking(Builder $query): void
    {
        $query->where(fn (Builder $query) => $query
            ->whereIn('status', [self::STATUS_PAID, self::STATUS_CONFIRMED])
            ->orWhere(fn (Builder $pending) => $pending
                ->where('status', self::STATUS_PENDING_PAYMENT)
                ->where('created_at', '>=', now()->subMinutes(self::PAYMENT_WINDOW_MINUTES))));
    }

    /**
     * Money actually collected and kept: paid and not refunded.
     */
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
        return self::DISPLAY_STATUSES[$this->displayStatus()][0] ?? $this->status;
    }

    public function statusColor(): string
    {
        return self::DISPLAY_STATUSES[$this->displayStatus()][1] ?? 'secondary';
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

    public function isFinished(): bool
    {
        return $this->endsAt()->lte(now());
    }

    /**
     * Still holds the slot and has not been played yet.
     */
    public function canBeCancelled(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING_PAYMENT, self::STATUS_CONFIRMED, self::STATUS_PAID], true)
            && ! $this->isExpired()
            && ! $this->isFinished();
    }

    /**
     * Payment collected at the venue (cash/transfer) for a booking that is not paid yet.
     */
    public function canRegisterPayment(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING_PAYMENT, self::STATUS_CONFIRMED], true)
            && ! $this->isExpired();
    }

    public function canBeRefunded(): bool
    {
        return $this->status === self::STATUS_CANCELLED && $this->paid_at !== null && $this->refunded_at === null;
    }

    public function reference(): string
    {
        return sprintf('MD-%06d', $this->id);
    }

    /**
     * Other reservations booked and paid together with this one (same booking code).
     *
     * @return Collection<int, self>
     */
    public function siblings()
    {
        if ($this->booking_code === null) {
            return $this->newCollection();
        }

        return self::query()
            ->with(['field:id,name', 'sport:id,name'])
            ->where('booking_code', $this->booking_code)
            ->whereKeyNot($this->id)
            ->orderBy('reserved_on')
            ->orderBy('starts_at')
            ->get();
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

    /**
     * Whether [$start, $end) on the reservation's date overlaps it.
     */
    public function overlaps(Carbon $start, Carbon $end): bool
    {
        return $this->startsAt()->lt($end) && $this->endsAt()->gt($start);
    }
}
