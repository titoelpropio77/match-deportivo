<?php

namespace App\Http\Controllers\Api;

use App\Enums\EventKind;
use App\Http\Controllers\Controller;
use App\Http\Resources\EventSpaceReservationResource;
use App\Models\EventSpaceReservation;
use App\Services\EventSpaceBookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Event space reservations of the signed-in user, paid with the simulated QR.
 */
class EventSpaceReservationController extends Controller
{
    private const MAX_HOURS = 12;

    public function store(Request $request, EventSpaceBookingService $bookings): JsonResponse
    {
        $validated = $request->validate([
            'event_space_id' => ['required', 'integer', 'exists:event_spaces,id'],
            'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'start_time' => ['required', 'date_format:H:i'],
            'hours' => ['required', 'integer', 'between:1,'.self::MAX_HOURS],
            'guests' => ['required', 'integer', 'min:1'],
            'event_type' => ['nullable', Rule::enum(EventKind::class)],
            'notes' => ['nullable', 'string', 'max:500'],
        ], [
            'hours.between' => 'Puedes reservar entre 1 y '.self::MAX_HOURS.' horas.',
        ]);

        $reservation = $bookings->book($request->user(), $validated);

        return response()->json(['data' => $this->resource($reservation)], 201);
    }

    /**
     * Upcoming first (soonest first), then past ones (latest first). Expired holds and the ones the
     * user cancelled are left out; venue cancellations stay so the user learns why.
     */
    public function mine(Request $request): JsonResponse
    {
        $now = now();

        $reservations = EventSpaceReservation::query()
            ->with(['space.court.city', 'space.court.photos'])
            ->where('user_id', $request->user()->id)
            ->where(fn ($query) => $query
                ->active()
                ->orWhere(fn ($cancelled) => $cancelled
                    ->where('status', EventSpaceReservation::STATUS_CANCELLED)
                    ->whereNotNull('cancelled_by')
                    ->whereColumn('cancelled_by', '!=', 'user_id')))
            ->get();

        [$upcoming, $past] = $reservations->partition(
            fn (EventSpaceReservation $reservation): bool => $reservation->endsAt()->gt($now)
                && $reservation->status !== EventSpaceReservation::STATUS_CANCELLED
        );

        $ordered = $upcoming->sortBy(fn (EventSpaceReservation $reservation) => $reservation->endsAt()->timestamp)
            ->concat($past->sortByDesc(fn (EventSpaceReservation $reservation) => $reservation->endsAt()->timestamp))
            ->values();

        return response()->json(['data' => EventSpaceReservationResource::collection($ordered)]);
    }

    /**
     * Simulated QR payment.
     * TODO: replace with the bank QR webhook once the payment provider is connected.
     */
    public function pay(Request $request, EventSpaceReservation $reservation): JsonResponse
    {
        abort_unless($reservation->user_id === $request->user()->id, 403);

        if ($reservation->status === EventSpaceReservation::STATUS_PAID) {
            return response()->json(['data' => $this->resource($reservation)]);
        }

        if ($reservation->status !== EventSpaceReservation::STATUS_PENDING_PAYMENT || $reservation->paymentExpired()) {
            throw ValidationException::withMessages([
                'reservation' => 'La reserva expiró o fue cancelada. Vuelve a elegir el horario.',
            ]);
        }

        $reservation->update([
            'status' => EventSpaceReservation::STATUS_PAID,
            'payment_method' => 'qr',
            'paid_at' => now(),
        ]);

        return response()->json(['data' => $this->resource($reservation)]);
    }

    /**
     * Releases an unpaid reservation (the user left the payment screen).
     */
    public function cancel(Request $request, EventSpaceReservation $reservation): JsonResponse
    {
        abort_unless($reservation->user_id === $request->user()->id, 403);

        if ($reservation->status !== EventSpaceReservation::STATUS_PENDING_PAYMENT) {
            throw ValidationException::withMessages([
                'reservation' => 'Solo se pueden cancelar reservas pendientes de pago.',
            ]);
        }

        $reservation->update([
            'status' => EventSpaceReservation::STATUS_CANCELLED,
            'cancelled_at' => now(),
            'cancelled_by' => $request->user()->id,
        ]);

        return response()->json(null, 204);
    }

    private function resource(EventSpaceReservation $reservation): EventSpaceReservationResource
    {
        return new EventSpaceReservationResource($reservation->load(['space.court.city', 'space.court.photos']));
    }
}
