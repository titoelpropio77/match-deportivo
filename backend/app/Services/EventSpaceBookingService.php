<?php

namespace App\Services;

use App\Models\EventSpace;
use App\Models\EventSpaceReservation;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Books one hour range of an event space, held as pending payment until the (simulated) QR is paid.
 */
class EventSpaceBookingService
{
    /**
     * @param  array{event_space_id: int, date: string, start_time: string, hours: int, guests: int, event_type?: string|null, notes?: string|null}  $data
     */
    public function book(User $user, array $data): EventSpaceReservation
    {
        return DB::transaction(function () use ($user, $data): EventSpaceReservation {
            $space = EventSpace::query()
                ->with('court')
                ->whereKey($data['event_space_id'])
                ->lockForUpdate()
                ->firstOrFail();

            $start = Carbon::parse($data['date'].' '.$data['start_time']);
            $end = $start->copy()->addHours((int) $data['hours']);

            $this->assertBookable($space, $data, $start, $end);

            return EventSpaceReservation::query()->create([
                'code' => $this->newCode(),
                'event_space_id' => $space->id,
                'user_id' => $user->id,
                'reserved_on' => $start->toDateString(),
                'starts_at' => $start->format('H:i:s'),
                'ends_at' => $end->format('H:i:s'),
                'hours' => (int) $data['hours'],
                'guests' => (int) $data['guests'],
                'event_type' => $data['event_type'] ?? null,
                'amount' => (float) $space->price_per_hour * (int) $data['hours'],
                'status' => EventSpaceReservation::STATUS_PENDING_PAYMENT,
                'notes' => $data['notes'] ?? null,
            ]);
        });
    }

    /**
     * @param  array{date: string, hours: int, guests: int}  $data
     */
    private function assertBookable(EventSpace $space, array $data, Carbon $start, Carbon $end): void
    {
        if (! $space->is_active) {
            $this->fail('event_space_id', "{$space->name} no está disponible para reservas.");
        }

        if ((int) $data['guests'] > $space->capacity) {
            $this->fail('guests', "{$space->name} recibe como máximo {$space->capacity} personas.");
        }

        if ((int) $data['hours'] < $space->min_hours) {
            $this->fail('hours', "{$space->name} se alquila por un mínimo de {$space->min_hours} horas.");
        }

        $opening = Carbon::parse($data['date'].' '.$space->openingTime());
        $closing = Carbon::parse($data['date'].' '.$space->closingTime());
        if ($start->lt($opening) || $end->gt($closing) || $start->minute !== 0) {
            $this->fail('start_time', 'El horario está fuera de la atención del espacio ('
                .substr($space->openingTime(), 0, 5).' – '.substr($space->closingTime(), 0, 5).').');
        }

        if ($start->lte(now())) {
            $this->fail('start_time', 'Ese horario ya pasó.');
        }

        $taken = EventSpaceReservation::query()
            ->where('event_space_id', $space->id)
            ->whereDate('reserved_on', $data['date'])
            ->active()
            ->lockForUpdate()
            ->get()
            ->contains(fn (EventSpaceReservation $existing): bool => $existing->overlaps($start, $end));

        if ($taken) {
            $this->fail('start_time', "{$space->name} ya está reservado en ese horario.");
        }
    }

    private function newCode(): string
    {
        do {
            $code = 'E'.Str::upper(Str::random(7));
        } while (EventSpaceReservation::query()->where('code', $code)->exists());

        return $code;
    }

    private function fail(string $key, string $message): never
    {
        throw ValidationException::withMessages([$key => $message]);
    }
}
