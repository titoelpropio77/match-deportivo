<?php

namespace App\Http\Controllers;

use App\DataTables\EventReservationDataTable;
use App\DataTables\ReservationDataTable;
use App\Http\Requests\EventReservations\StoreEventReservationRequest;
use App\Http\Requests\Reservations\CancelReservationRequest;
use App\Http\Requests\Reservations\RegisterPaymentRequest;
use App\Models\Court;
use App\Models\CourtReservation;
use App\Models\EventSpace;
use App\Models\EventSpaceReservation;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Reservations of event spaces (grill areas, halls...) of the venues the user can see.
 */
class EventReservationController extends Controller
{
    public function index(Request $request, EventReservationDataTable $dataTable): mixed
    {
        return $dataTable->render('event-reservations.index', [
            'courts' => $this->visibleCourts($request->user()),
            'statuses' => ReservationDataTable::STATUS_FILTERS,
            'filters' => $request->only(['court_id', 'status', 'date_from', 'date_to']),
        ]);
    }

    public function create(Request $request): View
    {
        $courts = $this->visibleCourts($request->user())->load(['eventSpaces' => fn ($spaces) => $spaces->where('is_active', true)]);

        return view('event-reservations.create', [
            'courts' => $courts,
            'maxHours' => StoreEventReservationRequest::MAX_HOURS,
            'paymentMethods' => CourtReservation::PAYMENT_METHODS,
            'prefill' => $request->only(['event_space_id', 'date']),
        ]);
    }

    /**
     * Walk-in or phone booking registered by venue staff, like ReservationController::store.
     */
    public function store(StoreEventReservationRequest $request): RedirectResponse
    {
        $validated = $request->validated();

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

    public function cancel(CancelReservationRequest $request, EventSpaceReservation $reservation): RedirectResponse
    {
        $this->authorizeReservation($reservation);

        $validated = $request->validated();

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

    public function registerPayment(RegisterPaymentRequest $request, EventSpaceReservation $reservation): RedirectResponse
    {
        $this->authorizeReservation($reservation);

        $validated = $request->validated();

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
