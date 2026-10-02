<?php

namespace App\Http\Controllers\Api;

use App\Enums\MatchStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\CourtFieldResource;
use App\Http\Resources\CourtReservationResource;
use App\Models\CourtField;
use App\Models\CourtReservation;
use App\Models\MatchModel;
use App\Services\CourtBookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class CourtFieldController extends Controller
{
    /**
     * List bookable courts, optionally filtered by city, sport and a date that still has free hours.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sport_id' => ['nullable', 'integer', 'exists:sports,id'],
            'city_id' => ['nullable', 'integer', 'exists:cities,id'],
            'date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $fields = CourtField::query()
            ->with([
                'sports',
                'court' => fn ($court) => $court->withCount(['eventSpaces' => fn ($spaces) => $spaces->active()]),
                'court.photos',
                'court.city',
            ])
            ->when(
                isset($validated['city_id']),
                fn ($query) => $query->whereHas('court', fn ($court) => $court->where('city_id', $validated['city_id']))
            )
            ->when(
                isset($validated['sport_id']),
                fn ($query) => $query->whereHas(
                    'sports',
                    fn ($sports) => $sports->where('sports.id', $validated['sport_id'])
                )
            )
            ->orderBy('name')
            ->get()
            ->sortBy(fn (CourtField $field) => $field->court->name)
            ->values();

        if (isset($validated['date'])) {
            $fields = $fields
                ->filter(function (CourtField $field) use ($validated): bool {
                    $slots = $field->slotsForDate($validated['date']);

                    return collect($slots)->contains(fn (array $slot): bool => $slot['available']);
                })
                ->values();
        }

        return response()->json([
            'data' => CourtFieldResource::collection($fields),
        ]);
    }

    /**
     * Hourly availability for one physical court on a given date.
     */
    public function availability(Request $request, CourtField $courtField): JsonResponse
    {
        $validated = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
        ]);

        $courtField->load(['sports', 'court.city']);
        $slots = $courtField->slotsForDate($validated['date']);

        // Other bookable courts of the same sports center, so the app can switch between them.
        $venueFields = CourtField::query()
            ->with('sports')
            ->where('court_id', $courtField->court_id)
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => [
                'field' => new CourtFieldResource($courtField),
                'date' => $validated['date'],
                'slots' => $slots,
                'free_ranges' => CourtField::freeRanges($slots),
                'venue_fields' => CourtFieldResource::collection($venueFields),
            ],
        ]);
    }

    /**
     * Reserve one hour range on one court. Kept for older app versions; new clients use
     * CourtBookingController::store to book several courts/ranges at once.
     */
    public function store(Request $request, CourtBookingService $bookings): JsonResponse
    {
        $validated = $request->validate([
            'court_field_id' => ['required', 'integer', 'exists:court_fields,id'],
            'sport_id' => ['required', 'integer', 'exists:sports,id'],
            'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'start_time' => ['required', 'date_format:H:i'],
            'hours' => ['required', 'integer', 'between:1,24'],
        ]);

        $reservation = $bookings->book($request->user(), [$validated], '')->first();
        $reservation->load(['field.court', 'sport']);

        return response()->json([
            'data' => new CourtReservationResource($reservation),
        ], 201);
    }

    /**
     * Reservations of the current user: upcoming ones first (soonest first), then past ones (latest first).
     * Expired unpaid reservations and the ones the player cancelled are left out; venue cancellations stay.
     */
    public function mine(Request $request): JsonResponse
    {
        $now = now();

        $reservations = CourtReservation::query()
            ->with(['field.court.photos', 'field.court.city', 'field.sports', 'sport', 'items'])
            ->where('user_id', $request->user()->id)
            ->where(fn ($query) => $query
                ->active()
                // Keep venue cancellations visible so the player learns why the booking is gone.
                ->orWhere(fn ($cancelled) => $cancelled
                    ->where('status', CourtReservation::STATUS_CANCELLED)
                    ->whereNotNull('cancelled_by')
                    ->whereColumn('cancelled_by', '!=', 'user_id')))
            ->get()
            ->map(fn (CourtReservation $reservation): array => [
                $reservation,
                Carbon::parse($reservation->reserved_on->toDateString().' '.$reservation->ends_at),
            ]);

        [$upcoming, $past] = $reservations->partition(
            fn (array $item): bool => $item[1]->gt($now) && $item[0]->status !== CourtReservation::STATUS_CANCELLED
        );

        $ordered = $upcoming->sortBy(fn (array $item) => $item[1]->timestamp)
            ->concat($past->sortByDesc(fn (array $item) => $item[1]->timestamp))
            ->map(fn (array $item): CourtReservation => $item[0])
            ->values();

        // Match the user already created from each booking ("Ver cancha creada" in the app).
        $matchIds = MatchModel::query()
            ->where('organizer_id', $request->user()->id)
            ->whereIn('booking_code', $ordered->pluck('booking_code')->filter()->unique())
            ->where('status', '!=', MatchStatus::Cancelled->value)
            ->pluck('id', 'booking_code');
        $ordered->each(fn (CourtReservation $reservation) => $reservation->setAttribute(
            'match_id',
            $reservation->booking_code ? $matchIds->get($reservation->booking_code) : null
        ));

        return response()->json([
            'data' => CourtReservationResource::collection($ordered),
        ]);
    }

    /**
     * Simulated QR payment: confirms a pending reservation of the current user.
     * TODO: replace with the bank QR webhook once the payment provider is connected.
     */
    public function pay(Request $request, CourtReservation $reservation): JsonResponse
    {
        abort_unless($reservation->user_id === $request->user()->id, 403);

        if ($reservation->status === CourtReservation::STATUS_PAID) {
            $reservation->load(['field.court', 'sport']);

            return response()->json(['data' => new CourtReservationResource($reservation)]);
        }

        if ($reservation->status !== CourtReservation::STATUS_PENDING_PAYMENT || $reservation->paymentExpired()) {
            throw ValidationException::withMessages([
                'reservation' => 'La reserva expiró o fue cancelada. Vuelve a elegir el horario.',
            ]);
        }

        $reservation->update([
            'status' => CourtReservation::STATUS_PAID,
            'payment_method' => 'qr',
            'paid_at' => now(),
        ]);
        $reservation->load(['field.court', 'sport']);

        return response()->json(['data' => new CourtReservationResource($reservation)]);
    }

    /**
     * Releases an unpaid reservation when the user leaves the payment screen.
     */
    public function cancel(Request $request, CourtReservation $reservation): JsonResponse
    {
        abort_unless($reservation->user_id === $request->user()->id, 403);

        if ($reservation->status !== CourtReservation::STATUS_PENDING_PAYMENT) {
            throw ValidationException::withMessages([
                'reservation' => 'Solo se pueden cancelar reservas pendientes de pago.',
            ]);
        }

        $reservation->update([
            'status' => CourtReservation::STATUS_CANCELLED,
            'cancelled_at' => now(),
            'cancelled_by' => $request->user()->id,
        ]);

        return response()->json(null, 204);
    }
}
