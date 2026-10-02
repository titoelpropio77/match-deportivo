<?php

namespace App\Http\Controllers;

use App\DataTables\ReservationDataTable;
use App\Http\Requests\Reservations\AgendaRequest;
use App\Http\Requests\Reservations\CancelReservationRequest;
use App\Http\Requests\Reservations\RegisterPaymentRequest;
use App\Http\Requests\Reservations\StoreReservationRequest;
use App\Models\Court;
use App\Models\CourtField;
use App\Models\CourtReservation;
use App\Models\RentalItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Reservations of the venues the user can see: partners their own venues, managers the assigned ones.
 */
class ReservationController extends Controller
{
    public function index(Request $request, ReservationDataTable $dataTable): mixed
    {
        return $dataTable->render('reservations.index', [
            'courts' => $this->visibleCourts($request->user()),
            'statuses' => ReservationDataTable::STATUS_FILTERS,
            'sources' => CourtReservation::SOURCES,
            'filters' => $request->only(['court_id', 'status', 'source', 'date_from', 'date_to']),
        ]);
    }

    /**
     * Day grid of one venue: courts as columns, hours as rows.
     */
    public function agenda(AgendaRequest $request): View
    {
        $courts = $this->visibleCourts($request->user());
        $validated = $request->validated();

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
            'fields.features',
            'rentalItems' => fn ($items) => $items->where('is_active', true),
        ]);

        return view('reservations.create', [
            'courts' => $courts,
            'maxHours' => StoreReservationRequest::MAX_HOURS,
            'paymentMethods' => CourtReservation::PAYMENT_METHODS,
            'prefill' => $request->only(['court_field_id', 'date', 'start_time']),
        ]);
    }

    /**
     * Walk-in or phone booking registered by venue staff. Either linked to an app user (by email)
     * or just a customer name/phone. Paid now, or confirmed to be paid at the venue.
     */
    public function store(StoreReservationRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $field = CourtField::query()->with(['court', 'features'])->findOrFail($validated['court_field_id']);
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

            if (! empty($validated['air_conditioning']) && ! $field->offersAirConditioning()) {
                throw ValidationException::withMessages(['air_conditioning' => 'Esa cancha no ofrece aire acondicionado con costo extra.']);
            }
            $surcharges = $field->surchargesFor($start, $end, ! empty($validated['air_conditioning']));

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
                'amount' => (float) $field->price_per_hour * $validated['hours'] + $itemsAmount
                    + $surcharges['air_conditioning_amount'] + $surcharges['lighting_amount'],
                'items_amount' => $itemsAmount,
                ...$surcharges,
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
    public function cancel(CancelReservationRequest $request, CourtReservation $reservation): RedirectResponse
    {
        $this->authorizeReservation($reservation);

        $validated = $request->validated();

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
    public function registerPayment(RegisterPaymentRequest $request, CourtReservation $reservation): RedirectResponse
    {
        $this->authorizeReservation($reservation);

        $validated = $request->validated();

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
