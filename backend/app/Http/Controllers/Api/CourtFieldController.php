<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CourtFieldResource;
use App\Http\Resources\CourtReservationResource;
use App\Models\CourtField;
use App\Models\CourtReservation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
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
            ->with(['sports', 'court.photos', 'court.city'])
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
     * Reserve 1 or 2 free hours. The slot becomes occupied for every sport on that court.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'court_field_id' => ['required', 'integer', 'exists:court_fields,id'],
            'sport_id' => ['required', 'integer', 'exists:sports,id'],
            'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'start_time' => ['required', 'date_format:H:i'],
            'hours' => ['required', 'integer', 'in:1,2'],
        ]);

        $reservation = DB::transaction(function () use ($validated, $request): CourtReservation {
            $field = CourtField::query()
                ->with('court')
                ->lockForUpdate()
                ->findOrFail($validated['court_field_id']);

            $offersSport = $field->sports()->where('sports.id', $validated['sport_id'])->exists();
            if (! $offersSport) {
                throw ValidationException::withMessages([
                    'sport_id' => 'Esa cancha no ofrece el deporte seleccionado.',
                ]);
            }

            $start = Carbon::parse($validated['date'].' '.$validated['start_time']);
            $end = $start->copy()->addHours($validated['hours']);
            $opening = Carbon::parse($validated['date'].' '.$field->court->opening_time);
            $closing = Carbon::parse($validated['date'].' '.$field->court->closing_time);

            if ($start->lt($opening) || $end->gt($closing) || $start->minute !== 0) {
                throw ValidationException::withMessages([
                    'start_time' => 'El horario está fuera del horario de la cancha.',
                ]);
            }

            if ($start->lte(now())) {
                throw ValidationException::withMessages([
                    'start_time' => 'Ese horario ya pasó.',
                ]);
            }

            $overlaps = CourtReservation::query()
                ->where('court_field_id', $field->id)
                ->whereDate('reserved_on', $validated['date'])
                ->active()
                ->lockForUpdate()
                ->get()
                ->contains(fn (CourtReservation $existing): bool => $existing->overlaps($start, $end));

            if ($overlaps) {
                throw ValidationException::withMessages([
                    'start_time' => 'Ese horario ya está ocupado.',
                ]);
            }

            $amount = (float) $field->price_per_hour * $validated['hours'];

            return CourtReservation::query()->create([
                'court_field_id' => $field->id,
                'user_id' => $request->user()->id,
                'sport_id' => $validated['sport_id'],
                'reserved_on' => $validated['date'],
                'starts_at' => $start->format('H:i:s'),
                'ends_at' => $end->format('H:i:s'),
                'hours' => $validated['hours'],
                'amount' => $amount,
                'status' => CourtReservation::STATUS_PENDING_PAYMENT,
            ]);
        });

        $reservation->load(['field.court', 'sport']);

        return response()->json([
            'data' => new CourtReservationResource($reservation),
        ], 201);
    }

    /**
     * Reservations of the current user: upcoming ones first (soonest first), then past ones (latest first).
     * Cancelled and expired unpaid reservations are left out.
     */
    public function mine(Request $request): JsonResponse
    {
        $now = now();

        $reservations = CourtReservation::query()
            ->with(['field.court.photos', 'field.court.city', 'field.sports', 'sport'])
            ->where('user_id', $request->user()->id)
            ->active()
            ->get()
            ->map(fn (CourtReservation $reservation): array => [
                $reservation,
                Carbon::parse($reservation->reserved_on->toDateString().' '.$reservation->ends_at),
            ]);

        [$upcoming, $past] = $reservations->partition(fn (array $item): bool => $item[1]->gt($now));

        $ordered = $upcoming->sortBy(fn (array $item) => $item[1]->timestamp)
            ->concat($past->sortByDesc(fn (array $item) => $item[1]->timestamp))
            ->map(fn (array $item): CourtReservation => $item[0])
            ->values();

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

        $reservation->update(['status' => CourtReservation::STATUS_PAID]);
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

        $reservation->update(['status' => CourtReservation::STATUS_CANCELLED]);

        return response()->json(null, 204);
    }
}
