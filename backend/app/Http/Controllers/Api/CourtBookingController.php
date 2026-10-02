<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CourtReservationResource;
use App\Models\CourtReservation;
use App\Services\CourtBookingService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * A booking groups the reservations a player books together (several courts and/or hour ranges)
 * under one booking code, paid with a single QR.
 */
class CourtBookingController extends Controller
{
    private const MAX_ITEMS = 20;

    public function store(Request $request, CourtBookingService $bookings): JsonResponse
    {
        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:'.self::MAX_ITEMS],
            'items.*.court_field_id' => ['required', 'integer', 'exists:court_fields,id'],
            'items.*.sport_id' => ['required', 'integer', 'exists:sports,id'],
            'items.*.date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'items.*.start_time' => ['required', 'date_format:H:i'],
            'items.*.hours' => ['required', 'integer', 'between:1,24'],
            'items.*.rentals' => ['sometimes', 'array', 'max:10'],
            'items.*.rentals.*.rental_item_id' => ['required', 'integer', 'exists:rental_items,id'],
            'items.*.rentals.*.quantity' => ['required', 'integer', 'between:1,20'],
        ], [
            'items.max' => 'Puedes reservar como máximo '.self::MAX_ITEMS.' rangos de horario a la vez.',
        ]);

        $reservations = $bookings->book($request->user(), $validated['items']);

        return response()->json(['data' => $this->payload($reservations)], 201);
    }

    /**
     * Simulated QR payment of the whole booking.
     * TODO: replace with the bank QR webhook once the payment provider is connected.
     */
    public function pay(Request $request, string $code): JsonResponse
    {
        $reservations = DB::transaction(function () use ($request, $code): Collection {
            $reservations = $this->bookingOf($request, $code, lock: true);

            if ($reservations->every(fn (CourtReservation $reservation) => $reservation->status === CourtReservation::STATUS_PAID)) {
                return $reservations;
            }

            $payable = $reservations->every(fn (CourtReservation $reservation) => $reservation->status === CourtReservation::STATUS_PENDING_PAYMENT
                && ! $reservation->paymentExpired());
            if (! $payable) {
                throw ValidationException::withMessages([
                    'booking' => 'La reserva expiró o fue cancelada. Vuelve a elegir los horarios.',
                ]);
            }

            CourtReservation::query()->whereKey($reservations->modelKeys())->update([
                'status' => CourtReservation::STATUS_PAID,
                'payment_method' => 'qr',
                'paid_at' => now(),
            ]);

            return $reservations->fresh();
        });

        return response()->json(['data' => $this->payload($reservations)]);
    }

    /**
     * Releases every unpaid reservation of the booking.
     */
    public function cancel(Request $request, string $code): JsonResponse
    {
        $reservations = $this->bookingOf($request, $code);

        $pending = $reservations->where('status', CourtReservation::STATUS_PENDING_PAYMENT);
        if ($pending->isEmpty()) {
            throw ValidationException::withMessages([
                'booking' => 'Solo se pueden cancelar reservas pendientes de pago.',
            ]);
        }

        CourtReservation::query()->whereKey($pending->modelKeys())->update([
            'status' => CourtReservation::STATUS_CANCELLED,
            'cancelled_at' => now(),
            'cancelled_by' => $request->user()->id,
        ]);

        return response()->json(null, 204);
    }

    /**
     * @return Collection<int, CourtReservation>
     */
    private function bookingOf(Request $request, string $code, bool $lock = false): Collection
    {
        $reservations = CourtReservation::query()
            ->where('booking_code', $code)
            ->when($lock, fn ($query) => $query->lockForUpdate())
            ->orderBy('reserved_on')
            ->orderBy('starts_at')
            ->get();

        abort_if($reservations->isEmpty(), 404);
        abort_unless($reservations->every(fn (CourtReservation $reservation) => $reservation->user_id === $request->user()->id), 403);

        return $reservations;
    }

    /**
     * @param  Collection<int, CourtReservation>  $reservations
     * @return array<string, mixed>
     */
    private function payload(Collection $reservations): array
    {
        $reservations->load(['field.court', 'sport', 'items']);
        $first = $reservations->first();

        return [
            'code' => $first->booking_code,
            'amount' => (float) $reservations->sum('amount'),
            'hours' => (int) $reservations->sum('hours'),
            'status' => $reservations->every(fn (CourtReservation $reservation) => $reservation->status === CourtReservation::STATUS_PAID)
                ? CourtReservation::STATUS_PAID
                : CourtReservation::STATUS_PENDING_PAYMENT,
            'payment_reference' => $first->booking_code,
            'payment_expires_at' => $reservations->min('created_at')
                ?->copy()
                ->addMinutes(CourtReservation::PAYMENT_WINDOW_MINUTES)
                ->toIso8601String(),
            'reservations' => CourtReservationResource::collection($reservations)->resolve(),
        ];
    }
}
