<?php

namespace App\Http\Controllers;

use App\Enums\EventKind;
use App\Models\Court;
use App\Models\CourtReservation;
use App\Models\EventSpace;
use App\Models\EventSpaceReservation;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

/**
 * Reservations of event spaces (grill areas, halls...) of the venues the user can see.
 */
class EventReservationController extends Controller
{
    private const MAX_HOURS = 12;

    /**
     * Filters of the list => label. Some are derived (expired, refund_pending), see applyStatusFilter().
     */
    private const STATUS_FILTERS = [
        'active' => 'Activas (ocupan horario)',
        'pending_payment' => 'Pago pendiente',
        'confirmed' => 'Confirmadas (pagan en el local)',
        'paid' => 'Pagadas',
        'cancelled' => 'Anuladas',
        'refund_pending' => 'Devolución pendiente',
        'expired' => 'Expiradas (sin pago)',
    ];

    public function index(Request $request): View
    {
        return view('event-reservations.index', [
            'courts' => $this->visibleCourts($request->user()),
            'statuses' => self::STATUS_FILTERS,
            'filters' => $request->only(['court_id', 'status', 'date_from', 'date_to']),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'court_id' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::in(array_keys(self::STATUS_FILTERS))],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $query = EventSpaceReservation::query()
            ->visibleTo($request->user())
            ->select('event_space_reservations.*')
            ->with(['space.court:id,name', 'user:id,name,email,phone'])
            ->when($filters['court_id'] ?? null, fn (Builder $query, $courtId) => $query
                ->whereHas('space', fn (Builder $space) => $space->where('court_id', $courtId)))
            ->when($filters['date_from'] ?? null, fn (Builder $query, $from) => $query->whereDate('reserved_on', '>=', $from))
            ->when($filters['date_to'] ?? null, fn (Builder $query, $to) => $query->whereDate('reserved_on', '<=', $to));
        $this->applyStatusFilter($query, $filters['status'] ?? null);

        return DataTables::eloquent($query)
            ->editColumn('reserved_on', fn (EventSpaceReservation $reservation) => $reservation->reserved_on->format('d/m/Y'))
            ->addColumn('schedule', fn (EventSpaceReservation $reservation) => $reservation->timeRange())
            ->addColumn('venue', fn (EventSpaceReservation $reservation) => e($reservation->space->court->name)
                .'<br><small class="text-muted">'.e($reservation->space->name).'</small>')
            ->filterColumn('venue', function (Builder $query, string $keyword): void {
                $query->whereHas('space', fn (Builder $space) => $space
                    ->where('name', 'ilike', "%{$keyword}%")
                    ->orWhereHas('court', fn (Builder $court) => $court->where('name', 'ilike', "%{$keyword}%")));
            })
            ->addColumn('event', fn (EventSpaceReservation $reservation) => e($reservation->event_type?->label() ?? '—')
                .'<br><small class="text-muted">'.$reservation->guests.' personas</small>')
            ->addColumn('customer', fn (EventSpaceReservation $reservation) => e($reservation->customerName())
                .($reservation->customerPhone() ? '<br><small class="text-muted">'.e($reservation->customerPhone()).'</small>' : ''))
            ->filterColumn('customer', function (Builder $query, string $keyword): void {
                $query->where(fn (Builder $customer) => $customer
                    ->where('customer_name', 'ilike', "%{$keyword}%")
                    ->orWhere('customer_phone', 'ilike', "%{$keyword}%")
                    ->orWhereHas('user', fn (Builder $user) => $user
                        ->where('name', 'ilike', "%{$keyword}%")
                        ->orWhere('email', 'ilike', "%{$keyword}%")));
            })
            ->editColumn('amount', fn (EventSpaceReservation $reservation) => 'Bs '.number_format((float) $reservation->amount, 2))
            ->addColumn('status_badge', fn (EventSpaceReservation $reservation) => view('event-reservations.partials.status', ['reservation' => $reservation])->render())
            ->editColumn('source', fn (EventSpaceReservation $reservation) => $reservation->source === 'admin' ? 'Panel' : 'App')
            ->addColumn('action', fn (EventSpaceReservation $reservation) => view('event-reservations.partials.actions', ['reservation' => $reservation])->render())
            ->orderColumn('reserved_on', fn (Builder $query, string $direction) => $this->latestFirst(
                $query->orderBy('event_space_reservations.reserved_on', $direction)->orderBy('event_space_reservations.starts_at', $direction)
            ))
            ->orderColumn('amount', fn (Builder $query, string $direction) => $this->latestFirst(
                $query->orderBy('event_space_reservations.amount', $direction)
            ))
            ->rawColumns(['venue', 'event', 'customer', 'status_badge', 'action'])
            ->toJson();
    }

    public function create(Request $request): View
    {
        $courts = $this->visibleCourts($request->user())->load(['eventSpaces' => fn ($spaces) => $spaces->where('is_active', true)]);

        return view('event-reservations.create', [
            'courts' => $courts,
            'maxHours' => self::MAX_HOURS,
            'paymentMethods' => CourtReservation::PAYMENT_METHODS,
            'prefill' => $request->only(['event_space_id', 'date']),
        ]);
    }

    /**
     * Walk-in or phone booking registered by venue staff, like ReservationController::store.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'event_space_id' => ['required', 'integer', 'exists:event_spaces,id'],
            'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'start_time' => ['required', 'date_format:H:i'],
            'hours' => ['required', 'integer', 'between:1,'.self::MAX_HOURS],
            'guests' => ['required', 'integer', 'min:1'],
            'event_type' => ['nullable', Rule::enum(EventKind::class)],
            'user_email' => ['nullable', 'email', 'exists:users,email'],
            'customer_name' => ['required_without:user_email', 'nullable', 'string', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:30'],
            'payment' => ['required', Rule::in(['venue', ...array_keys(CourtReservation::PAYMENT_METHODS)])],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], [
            'user_email.exists' => 'No hay ningún usuario de la app con ese email.',
            'customer_name.required_without' => 'Indica el nombre del cliente o el email de su cuenta en la app.',
        ], [
            'event_space_id' => 'espacio',
            'date' => 'fecha',
            'start_time' => 'hora de inicio',
            'hours' => 'horas',
            'guests' => 'personas',
            'event_type' => 'tipo de evento',
            'user_email' => 'email del cliente',
            'customer_name' => 'nombre del cliente',
            'customer_phone' => 'teléfono',
            'payment' => 'pago',
            'notes' => 'notas',
        ]);

        $space = EventSpace::query()->with('court')->findOrFail($validated['event_space_id']);
        Gate::authorize('manage', $space->court);

        $reservation = DB::transaction(function () use ($validated, $space, $request): EventSpaceReservation {
            EventSpace::query()->whereKey($space->id)->lockForUpdate()->first();

            if ((int) $validated['guests'] > $space->capacity) {
                throw ValidationException::withMessages(['guests' => "{$space->name} recibe como máximo {$space->capacity} personas."]);
            }

            $start = Carbon::parse($validated['date'].' '.$validated['start_time']);
            $end = $start->copy()->addHours((int) $validated['hours']);
            $this->assertSlotIsFree($space, $start, $end);

            $paidNow = $validated['payment'] !== 'venue';
            $user = isset($validated['user_email'])
                ? User::query()->where('email', $validated['user_email'])->first()
                : null;

            return EventSpaceReservation::query()->create([
                'code' => $this->newCode(),
                'event_space_id' => $space->id,
                'user_id' => $user?->id,
                'customer_name' => $validated['customer_name'] ?? null,
                'customer_phone' => $validated['customer_phone'] ?? null,
                'reserved_on' => $validated['date'],
                'starts_at' => $start->format('H:i:s'),
                'ends_at' => $end->format('H:i:s'),
                'hours' => $validated['hours'],
                'guests' => $validated['guests'],
                'event_type' => $validated['event_type'] ?? null,
                'amount' => (float) $space->price_per_hour * $validated['hours'],
                'status' => $paidNow ? EventSpaceReservation::STATUS_PAID : EventSpaceReservation::STATUS_CONFIRMED,
                'source' => 'admin',
                'payment_method' => $paidNow ? $validated['payment'] : null,
                'paid_at' => $paidNow ? now() : null,
                'created_by' => $request->user()->id,
                'notes' => $validated['notes'] ?? null,
            ]);
        });

        return redirect()->route('event-reservations.show', $reservation)
            ->with('success', "Reserva {$reservation->code} registrada.");
    }

    public function show(EventSpaceReservation $reservation): View
    {
        $this->authorizeReservation($reservation);

        $reservation->load(['space.court', 'user', 'cancelledBy', 'createdBy']);

        return view('event-reservations.show', [
            'reservation' => $reservation,
            'paymentMethods' => CourtReservation::PAYMENT_METHODS,
        ]);
    }

    public function cancel(Request $request, EventSpaceReservation $reservation): RedirectResponse
    {
        $this->authorizeReservation($reservation);

        $validated = $request->validateWithBag('cancel', [
            'cancellation_reason' => ['required', 'string', 'min:5', 'max:500'],
        ], [], ['cancellation_reason' => 'motivo de anulación']);

        if (! $reservation->canBeCancelled()) {
            return back()->with('error', 'Esta reserva ya no se puede anular.');
        }

        $reservation->update([
            'status' => EventSpaceReservation::STATUS_CANCELLED,
            'cancelled_at' => now(),
            'cancelled_by' => $request->user()->id,
            'cancellation_reason' => $validated['cancellation_reason'],
        ]);

        $message = "Reserva {$reservation->code} anulada. El horario quedó libre.";
        if ($reservation->paid_at !== null) {
            $message .= ' Recuerda devolver Bs '.number_format((float) $reservation->amount, 2).' al cliente.';
        }

        return redirect()->route('event-reservations.show', $reservation)->with('success', $message);
    }

    public function registerPayment(Request $request, EventSpaceReservation $reservation): RedirectResponse
    {
        $this->authorizeReservation($reservation);

        $validated = $request->validateWithBag('payment', [
            'payment_method' => ['required', Rule::in(array_keys(CourtReservation::PAYMENT_METHODS))],
        ], [], ['payment_method' => 'método de pago']);

        if (! $reservation->canRegisterPayment()) {
            return back()->with('error', 'Esta reserva no tiene un pago pendiente.');
        }

        $reservation->update([
            'status' => EventSpaceReservation::STATUS_PAID,
            'payment_method' => $validated['payment_method'],
            'paid_at' => now(),
        ]);

        return redirect()->route('event-reservations.show', $reservation)
            ->with('success', "Pago de la reserva {$reservation->code} registrado.");
    }

    public function refund(EventSpaceReservation $reservation): RedirectResponse
    {
        $this->authorizeReservation($reservation);

        if (! $reservation->canBeRefunded()) {
            return back()->with('error', 'Esta reserva no tiene una devolución pendiente.');
        }

        $reservation->update(['refunded_at' => now()]);

        return redirect()->route('event-reservations.show', $reservation)
            ->with('success', "Devolución de la reserva {$reservation->code} registrada.");
    }

    private function authorizeReservation(EventSpaceReservation $reservation): void
    {
        Gate::authorize('manage', $reservation->space()->with('court')->firstOrFail()->court);
    }

    /**
     * @return Collection<int, Court>
     */
    private function visibleCourts(User $user)
    {
        return Court::query()->visibleTo($user)->orderBy('name')->get();
    }

    private function latestFirst(Builder $query): Builder
    {
        return $query->orderByDesc('event_space_reservations.reserved_on')
            ->orderByDesc('event_space_reservations.starts_at')
            ->orderByDesc('event_space_reservations.id');
    }

    private function applyStatusFilter(Builder $query, ?string $status): void
    {
        $expiredBefore = now()->subMinutes(EventSpaceReservation::PAYMENT_WINDOW_MINUTES);

        match ($status) {
            'active' => $query->blocking(),
            'pending_payment' => $query->where('status', EventSpaceReservation::STATUS_PENDING_PAYMENT)
                ->where('event_space_reservations.created_at', '>=', $expiredBefore),
            'expired' => $query->where('status', EventSpaceReservation::STATUS_PENDING_PAYMENT)
                ->where('event_space_reservations.created_at', '<', $expiredBefore),
            'refund_pending' => $query->where('status', EventSpaceReservation::STATUS_CANCELLED)
                ->whereNotNull('paid_at')->whereNull('refunded_at'),
            'confirmed', 'paid', 'cancelled' => $query->where('status', $status),
            default => null,
        };
    }

    private function assertSlotIsFree(EventSpace $space, Carbon $start, Carbon $end): void
    {
        $date = $start->toDateString();
        $opening = Carbon::parse($date.' '.$space->openingTime());
        $closing = Carbon::parse($date.' '.$space->closingTime());

        if ($start->minute !== 0 || $start->lt($opening) || $end->gt($closing)) {
            throw ValidationException::withMessages([
                'start_time' => "El horario está fuera de la atención del espacio ({$space->openingTime()} – {$space->closingTime()}).",
            ]);
        }

        if ($start->lte(now())) {
            throw ValidationException::withMessages(['start_time' => 'Ese horario ya pasó.']);
        }

        $conflict = EventSpaceReservation::query()
            ->where('event_space_id', $space->id)
            ->whereDate('reserved_on', $date)
            ->blocking()
            ->get()
            ->first(fn (EventSpaceReservation $reservation) => $reservation->overlaps($start, $end));

        if ($conflict !== null) {
            throw ValidationException::withMessages([
                'start_time' => "Ese horario choca con la reserva {$conflict->code} ({$conflict->timeRange()}).",
            ]);
        }
    }

    private function newCode(): string
    {
        do {
            $code = 'E'.Str::upper(Str::random(7));
        } while (EventSpaceReservation::query()->where('code', $code)->exists());

        return $code;
    }
}
