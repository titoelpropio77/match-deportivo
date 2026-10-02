<?php

namespace App\Http\Controllers;

use App\Models\Court;
use App\Models\CourtField;
use App\Models\CourtReservation;
use App\Models\RentalItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

/**
 * Reservations of the venues the user can see: partners their own venues, managers the assigned ones.
 */
class ReservationController extends Controller
{
    /**
     * Longest booking staff can register at once (the app allows up to 2 hours).
     */
    private const MAX_HOURS = 4;

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
        return view('reservations.index', [
            'courts' => $this->visibleCourts($request->user()),
            'statuses' => self::STATUS_FILTERS,
            'sources' => CourtReservation::SOURCES,
            'filters' => $request->only(['court_id', 'status', 'source', 'date_from', 'date_to']),
        ]);
    }

    /**
     * Server-side DataTables source, filtered by venue, status, source and date range.
     */
    public function data(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'court_id' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::in(array_keys(self::STATUS_FILTERS))],
            'source' => ['nullable', Rule::in(array_keys(CourtReservation::SOURCES))],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $query = CourtReservation::query()
            ->visibleTo($request->user())
            ->select('court_reservations.*')
            ->with(['field.court:id,name', 'sport:id,name', 'user:id,name,email,phone'])
            ->when($filters['court_id'] ?? null, fn (Builder $query, $courtId) => $query
                ->whereHas('field', fn (Builder $field) => $field->where('court_id', $courtId)))
            ->when($filters['source'] ?? null, fn (Builder $query, $source) => $query->where('source', $source))
            ->when($filters['date_from'] ?? null, fn (Builder $query, $from) => $query->whereDate('reserved_on', '>=', $from))
            ->when($filters['date_to'] ?? null, fn (Builder $query, $to) => $query->whereDate('reserved_on', '<=', $to));
        $this->applyStatusFilter($query, $filters['status'] ?? null);

        return DataTables::eloquent($query)
            ->addColumn('reference', fn (CourtReservation $reservation) => $reservation->reference())
            ->filterColumn('reference', function (Builder $query, string $keyword): void {
                $id = (int) preg_replace('/\D/', '', $keyword);
                if ($id > 0) {
                    $query->where('court_reservations.id', $id);
                }
            })
            ->editColumn('reserved_on', fn (CourtReservation $reservation) => $reservation->reserved_on->format('d/m/Y'))
            ->addColumn('schedule', fn (CourtReservation $reservation) => $reservation->timeRange())
            ->addColumn('venue', fn (CourtReservation $reservation) => e($reservation->field->court->name)
                .'<br><small class="text-muted">'.e($reservation->field->name).'</small>')
            ->filterColumn('venue', function (Builder $query, string $keyword): void {
                $query->whereHas('field', fn (Builder $field) => $field
                    ->where('name', 'ilike', "%{$keyword}%")
                    ->orWhereHas('court', fn (Builder $court) => $court->where('name', 'ilike', "%{$keyword}%")));
            })
            ->addColumn('sport', fn (CourtReservation $reservation) => e($reservation->sport?->name ?? '—'))
            ->addColumn('customer', fn (CourtReservation $reservation) => e($reservation->customerName())
                .($reservation->customerPhone() ? '<br><small class="text-muted">'.e($reservation->customerPhone()).'</small>' : ''))
            ->filterColumn('customer', function (Builder $query, string $keyword): void {
                $query->where(fn (Builder $customer) => $customer
                    ->where('customer_name', 'ilike', "%{$keyword}%")
                    ->orWhere('customer_phone', 'ilike', "%{$keyword}%")
                    ->orWhereHas('user', fn (Builder $user) => $user
                        ->where('name', 'ilike', "%{$keyword}%")
                        ->orWhere('email', 'ilike', "%{$keyword}%")));
            })
            ->editColumn('amount', fn (CourtReservation $reservation) => 'Bs '.number_format((float) $reservation->amount, 2))
            ->addColumn('status_badge', fn (CourtReservation $reservation) => view('reservations.partials.status', ['reservation' => $reservation])->render())
            ->editColumn('source', fn (CourtReservation $reservation) => $reservation->source === 'admin' ? 'Panel' : 'App')
            ->addColumn('action', fn (CourtReservation $reservation) => view('reservations.partials.actions', ['reservation' => $reservation])->render())
            // Each sortable column falls back to date and time descending, so ties keep the latest first.
            ->orderColumn('reserved_on', fn (Builder $query, string $direction) => $this->latestFirst(
                $query->orderBy('court_reservations.reserved_on', $direction)->orderBy('court_reservations.starts_at', $direction)
            ))
            ->orderColumn('schedule', fn (Builder $query, string $direction) => $this->latestFirst(
                $query->orderBy('court_reservations.starts_at', $direction)
            ))
            ->orderColumn('amount', fn (Builder $query, string $direction) => $this->latestFirst(
                $query->orderBy('court_reservations.amount', $direction)
            ))
            ->orderColumn('source', fn (Builder $query, string $direction) => $this->latestFirst(
                $query->orderBy('court_reservations.source', $direction)
            ))
            ->rawColumns(['venue', 'customer', 'status_badge', 'action'])
            ->toJson();
    }

    /**
     * Day grid of one venue: courts as columns, hours as rows.
     */
    public function agenda(Request $request): View
    {
        $courts = $this->visibleCourts($request->user());
        $validated = $request->validate([
            'court_id' => ['nullable', 'integer'],
            'date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $court = $courts->firstWhere('id', (int) ($validated['court_id'] ?? 0)) ?? $courts->first();
        $date = Carbon::parse($validated['date'] ?? today()->toDateString())->startOfDay();

        $fields = collect();
        $hours = [];
        $grid = [];
        if ($court !== null) {
            $fields = $court->fields()->with('sports:id,name')->get();
            $reservations = CourtReservation::query()
                ->with(['sport:id,name', 'user:id,name'])
                ->whereIn('court_field_id', $fields->modelKeys())
                ->whereDate('reserved_on', $date->toDateString())
                ->blocking()
                ->get();

            $cursor = Carbon::parse($date->toDateString().' '.$court->opening_time);
            $closing = Carbon::parse($date->toDateString().' '.$court->closing_time);
            while ($cursor->copy()->addHour()->lte($closing)) {
                $end = $cursor->copy()->addHour();
                $hours[] = ['start' => $cursor->copy(), 'end' => $end];
                foreach ($fields as $field) {
                    $grid[$cursor->format('H:i')][$field->id] = $reservations->first(
                        fn (CourtReservation $reservation) => $reservation->court_field_id === $field->id
                            && $reservation->overlaps($cursor, $end)
                    );
                }
                $cursor = $end;
            }
        }

        return view('reservations.agenda', [
            'courts' => $courts,
            'court' => $court,
            'date' => $date,
            'fields' => $fields,
            'hours' => $hours,
            'grid' => $grid,
        ]);
    }

    public function create(Request $request): View
    {
        $courts = $this->visibleCourts($request->user())->load([
            'fields.sports:id,name',
            'rentalItems' => fn ($items) => $items->where('is_active', true),
        ]);

        return view('reservations.create', [
            'courts' => $courts,
            'maxHours' => self::MAX_HOURS,
            'paymentMethods' => CourtReservation::PAYMENT_METHODS,
            'prefill' => $request->only(['court_field_id', 'date', 'start_time']),
        ]);
    }

    /**
     * Walk-in or phone booking registered by venue staff. Either linked to an app user (by email)
     * or just a customer name/phone. Paid now, or confirmed to be paid at the venue.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'court_field_id' => ['required', 'integer', 'exists:court_fields,id'],
            'sport_id' => ['required', 'integer', 'exists:sports,id'],
            'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'start_time' => ['required', 'date_format:H:i'],
            'hours' => ['required', 'integer', 'between:1,'.self::MAX_HOURS],
            'user_email' => ['nullable', 'email', 'exists:users,email'],
            'customer_name' => ['required_without:user_email', 'nullable', 'string', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:30'],
            'payment' => ['required', Rule::in(['venue', ...array_keys(CourtReservation::PAYMENT_METHODS)])],
            'notes' => ['nullable', 'string', 'max:1000'],
            // rental_item_id => quantity (0 = not rented).
            'rentals' => ['sometimes', 'array'],
            'rentals.*' => ['nullable', 'integer', 'between:0,20'],
        ], [
            'user_email.exists' => 'No hay ningún usuario de la app con ese email.',
            'customer_name.required_without' => 'Indica el nombre del cliente o el email de su cuenta en la app.',
        ], [
            'court_field_id' => 'cancha',
            'sport_id' => 'deporte',
            'date' => 'fecha',
            'start_time' => 'hora de inicio',
            'hours' => 'horas',
            'user_email' => 'email del cliente',
            'customer_name' => 'nombre del cliente',
            'customer_phone' => 'teléfono',
            'payment' => 'pago',
            'notes' => 'notas',
        ]);

        $field = CourtField::query()->with('court')->findOrFail($validated['court_field_id']);
        Gate::authorize('manage', $field->court);

        $reservation = DB::transaction(function () use ($validated, $field, $request): CourtReservation {
            CourtField::query()->whereKey($field->id)->lockForUpdate()->first();

            if (! $field->sports()->where('sports.id', $validated['sport_id'])->exists()) {
                throw ValidationException::withMessages(['sport_id' => 'Esa cancha no ofrece el deporte seleccionado.']);
            }

            $start = Carbon::parse($validated['date'].' '.$validated['start_time']);
            $end = $start->copy()->addHours((int) $validated['hours']);
            $this->assertSlotIsFree($field, $start, $end);

            $paidNow = $validated['payment'] !== 'venue';
            $user = isset($validated['user_email'])
                ? User::query()->where('email', $validated['user_email'])->first()
                : null;

            $lines = $this->rentalLines($field, (int) $validated['sport_id'], $validated['rentals'] ?? [], $start, $end, (int) $validated['hours']);
            $itemsAmount = round(array_sum(array_column($lines, 'amount')), 2);

            $reservation = CourtReservation::query()->create([
                'court_field_id' => $field->id,
                'user_id' => $user?->id,
                'customer_name' => $validated['customer_name'] ?? null,
                'customer_phone' => $validated['customer_phone'] ?? null,
                'sport_id' => $validated['sport_id'],
                'reserved_on' => $validated['date'],
                'starts_at' => $start->format('H:i:s'),
                'ends_at' => $end->format('H:i:s'),
                'hours' => $validated['hours'],
                'amount' => (float) $field->price_per_hour * $validated['hours'] + $itemsAmount,
                'items_amount' => $itemsAmount,
                'status' => $paidNow ? CourtReservation::STATUS_PAID : CourtReservation::STATUS_CONFIRMED,
                'source' => 'admin',
                'payment_method' => $paidNow ? $validated['payment'] : null,
                'paid_at' => $paidNow ? now() : null,
                'created_by' => $request->user()->id,
                'notes' => $validated['notes'] ?? null,
            ]);
            $reservation->items()->createMany($lines);

            return $reservation;
        });

        return redirect()->route('reservations.show', $reservation)
            ->with('success', "Reserva {$reservation->reference()} registrada.");
    }

    public function show(CourtReservation $reservation): View
    {
        $this->authorizeReservation($reservation);

        $reservation->load(['field.court.owner', 'sport', 'user', 'cancelledBy', 'createdBy', 'items']);

        return view('reservations.show', [
            'reservation' => $reservation,
            'paymentMethods' => CourtReservation::PAYMENT_METHODS,
        ]);
    }

    /**
     * Cancel a booking that still holds its slot. The reason is shown to the player in the app;
     * if it was paid, it stays as "refund pending" until marked as refunded.
     */
    public function cancel(Request $request, CourtReservation $reservation): RedirectResponse
    {
        $this->authorizeReservation($reservation);

        $validated = $request->validateWithBag('cancel', [
            'cancellation_reason' => ['required', 'string', 'min:5', 'max:500'],
        ], [], ['cancellation_reason' => 'motivo de anulación']);

        if (! $reservation->canBeCancelled()) {
            return back()->with('error', 'Esta reserva ya no se puede anular.');
        }

        $reservation->update([
            'status' => CourtReservation::STATUS_CANCELLED,
            'cancelled_at' => now(),
            'cancelled_by' => $request->user()->id,
            'cancellation_reason' => $validated['cancellation_reason'],
        ]);

        $message = "Reserva {$reservation->reference()} anulada. El horario quedó libre.";
        if ($reservation->paid_at !== null) {
            $message .= ' Recuerda devolver Bs '.number_format((float) $reservation->amount, 2).' al cliente.';
        }

        return redirect()->route('reservations.show', $reservation)->with('success', $message);
    }

    /**
     * Payment collected at the venue for a confirmed or pending booking.
     */
    public function registerPayment(Request $request, CourtReservation $reservation): RedirectResponse
    {
        $this->authorizeReservation($reservation);

        $validated = $request->validateWithBag('payment', [
            'payment_method' => ['required', Rule::in(array_keys(CourtReservation::PAYMENT_METHODS))],
        ], [], ['payment_method' => 'método de pago']);

        if (! $reservation->canRegisterPayment()) {
            return back()->with('error', 'Esta reserva no tiene un pago pendiente.');
        }

        $reservation->update([
            'status' => CourtReservation::STATUS_PAID,
            'payment_method' => $validated['payment_method'],
            'paid_at' => now(),
        ]);

        return redirect()->route('reservations.show', $reservation)
            ->with('success', "Pago de la reserva {$reservation->reference()} registrado.");
    }

    public function refund(CourtReservation $reservation): RedirectResponse
    {
        $this->authorizeReservation($reservation);

        if (! $reservation->canBeRefunded()) {
            return back()->with('error', 'Esta reserva no tiene una devolución pendiente.');
        }

        $reservation->update(['refunded_at' => now()]);

        return redirect()->route('reservations.show', $reservation)
            ->with('success', "Devolución de la reserva {$reservation->reference()} registrada.");
    }

    private function authorizeReservation(CourtReservation $reservation): void
    {
        Gate::authorize('manage', $reservation->field()->with('court')->firstOrFail()->court);
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
        return $query->orderByDesc('court_reservations.reserved_on')
            ->orderByDesc('court_reservations.starts_at')
            ->orderByDesc('court_reservations.id');
    }

    private function applyStatusFilter(Builder $query, ?string $status): void
    {
        $expiredBefore = now()->subMinutes(CourtReservation::PAYMENT_WINDOW_MINUTES);

        match ($status) {
            'active' => $query->blocking(),
            'pending_payment' => $query->where('status', CourtReservation::STATUS_PENDING_PAYMENT)
                ->where('court_reservations.created_at', '>=', $expiredBefore),
            'expired' => $query->where('status', CourtReservation::STATUS_PENDING_PAYMENT)
                ->where('court_reservations.created_at', '<', $expiredBefore),
            'refund_pending' => $query->where('status', CourtReservation::STATUS_CANCELLED)
                ->whereNotNull('paid_at')->whereNull('refunded_at'),
            'confirmed', 'paid', 'cancelled' => $query->where('status', $status),
            default => null,
        };
    }

    /**
     * Rented gear of the venue for the reservation's sport, checking the units still free
     * (same rule as the backend's CourtBookingService).
     *
     * @param  array<int|string, int|string|null>  $rentals  rental_item_id => quantity
     * @return list<array<string, mixed>>
     */
    private function rentalLines(CourtField $field, int $sportId, array $rentals, Carbon $start, Carbon $end, int $hours): array
    {
        $quantities = collect($rentals)->map(fn ($quantity) => (int) $quantity)->filter(fn (int $quantity) => $quantity > 0);
        if ($quantities->isEmpty()) {
            return [];
        }

        $items = RentalItem::query()
            ->whereKey($quantities->keys())
            ->where('court_id', $field->court_id)
            ->where('sport_id', $sportId)
            ->where('is_active', true)
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        $lines = [];
        foreach ($quantities as $id => $quantity) {
            $item = $items->get($id);
            if ($item === null) {
                throw ValidationException::withMessages(['rentals' => 'Uno de los artículos no se alquila para esta cancha y deporte.']);
            }
            if ($item->stock !== null && $quantity > $item->stock - $item->reservedQuantity($start, $end)) {
                $free = max(0, $item->stock - $item->reservedQuantity($start, $end));
                throw ValidationException::withMessages(['rentals' => "Solo quedan {$free} de {$item->name} en ese horario."]);
            }
            $lines[] = [
                'rental_item_id' => $item->id,
                'name' => $item->name,
                'quantity' => $quantity,
                'unit_price' => $item->price,
                'price_type' => $item->price_type,
                'amount' => $item->amountFor($quantity, $hours),
            ];
        }

        return $lines;
    }

    private function assertSlotIsFree(CourtField $field, Carbon $start, Carbon $end): void
    {
        $date = $start->toDateString();
        $opening = Carbon::parse($date.' '.$field->court->opening_time);
        $closing = Carbon::parse($date.' '.$field->court->closing_time);

        if ($start->minute !== 0 || $start->lt($opening) || $end->gt($closing)) {
            throw ValidationException::withMessages([
                'start_time' => 'El horario está fuera del horario de atención ('
                    .substr($field->court->opening_time, 0, 5).' – '.substr($field->court->closing_time, 0, 5).').',
            ]);
        }

        if ($start->lte(now())) {
            throw ValidationException::withMessages(['start_time' => 'Ese horario ya pasó.']);
        }

        $conflict = CourtReservation::query()
            ->where('court_field_id', $field->id)
            ->whereDate('reserved_on', $date)
            ->blocking()
            ->get()
            ->first(fn (CourtReservation $reservation) => $reservation->overlaps($start, $end));

        if ($conflict !== null) {
            throw ValidationException::withMessages([
                'start_time' => "Ese horario choca con la reserva {$conflict->reference()} ({$conflict->timeRange()}).",
            ]);
        }
    }
}
