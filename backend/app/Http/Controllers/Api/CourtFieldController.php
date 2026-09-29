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
     * List bookable courts, optionally filtered by sport and a date that still has free hours.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sport_id' => ['nullable', 'integer', 'exists:sports,id'],
            'date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $fields = CourtField::query()
            ->with(['sports', 'court.photos'])
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

        $courtField->load(['sports', 'court']);
        $slots = $courtField->slotsForDate($validated['date']);

        return response()->json([
            'data' => [
                'field' => new CourtFieldResource($courtField),
                'date' => $validated['date'],
                'slots' => $slots,
                'free_ranges' => CourtField::freeRanges($slots),
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

            $overlaps = CourtReservation::query()
                ->where('court_field_id', $field->id)
                ->whereDate('reserved_on', $validated['date'])
                ->where('status', '!=', 'cancelled')
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
                'status' => 'pending_payment',
            ]);
        });

        $reservation->load(['field.court', 'sport']);

        return response()->json([
            'data' => new CourtReservationResource($reservation),
        ], 201);
    }
}
