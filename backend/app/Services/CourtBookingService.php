<?php

namespace App\Services;

use App\Models\CourtField;
use App\Models\CourtReservation;
use App\Models\RentalItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Books one or more hour ranges, on one or more courts, as a single booking paid with one QR.
 * Each range may add rented gear of its center and sport (balls, rackets...), charged with it,
 * and air conditioning when the court offers it; night hours add the court's lighting price.
 * All ranges are reserved or none: any conflict rejects the whole booking.
 */
class CourtBookingService
{
    /**
     * @param  list<array{court_field_id: int, sport_id: int, date: string, start_time: string, hours: int, air_conditioning?: bool, rentals?: list<array{rental_item_id: int, quantity: int}>}>  $items
     * @param  string  $errorPrefix  validation key prefix for item errors, e.g. "items." (or "" for a single item)
     * @return Collection<int, CourtReservation>
     */
    public function book(User $user, array $items, string $errorPrefix = 'items.'): Collection
    {
        return DB::transaction(function () use ($user, $items, $errorPrefix): Collection {
            $fields = CourtField::query()
                ->with(['court', 'sports:id', 'features'])
                ->whereIn('id', array_column($items, 'court_field_id'))
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $rentalItems = RentalItem::query()
                ->whereIn('id', collect($items)->pluck('rentals')->flatten(1)->pluck('rental_item_id')->filter()->unique())
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $code = $this->newCode();
            $accepted = [];
            $rented = [];
            $reservations = new Collection;

            foreach ($items as $index => $item) {
                $key = $errorPrefix === '' ? '' : $errorPrefix.$index.'.';
                $field = $fields->get($item['court_field_id']);
                [$start, $end] = $this->range($item);

                $this->assertBookable($field, $item, $start, $end, $key);

                foreach ($accepted as $other) {
                    if ($other['field_id'] === $field->id && $other['start']->lt($end) && $other['end']->gt($start)) {
                        $this->fail($key.'start_time', "Elegiste dos veces {$field->name} a las {$start->format('H:i')}.");
                    }
                }
                $accepted[] = ['field_id' => $field->id, 'start' => $start, 'end' => $end];

                $lines = [];
                // The same item picked twice in a range counts as one line.
                $rentals = collect($item['rentals'] ?? [])
                    ->groupBy('rental_item_id')
                    ->map(fn ($lines, $id): array => ['rental_item_id' => (int) $id, 'quantity' => (int) $lines->sum('quantity')])
                    ->values();
                foreach ($rentals as $rentalIndex => $rental) {
                    $rentalKey = $key.'rentals.'.$rentalIndex.'.';
                    $rentalItem = $rentalItems->get($rental['rental_item_id']);
                    $quantity = (int) $rental['quantity'];
                    $this->assertRentable($rentalItem, $field, $item, $quantity, $start, $end, $rented, $rentalKey);

                    $rented[] = ['item_id' => $rentalItem->id, 'start' => $start, 'end' => $end, 'quantity' => $quantity];
                    $lines[] = [
                        'rental_item_id' => $rentalItem->id,
                        'name' => $rentalItem->name,
                        'quantity' => $quantity,
                        'unit_price' => $rentalItem->price,
                        'price_type' => $rentalItem->price_type,
                        'amount' => $rentalItem->amountFor($quantity, (int) $item['hours']),
                    ];
                }
                $itemsAmount = round(array_sum(array_column($lines, 'amount')), 2);

                if (! empty($item['air_conditioning']) && ! $field->offersAirConditioning()) {
                    $this->fail($key.'air_conditioning', "{$field->name} no ofrece aire acondicionado.");
                }
                $surcharges = $field->surchargesFor($start, $end, ! empty($item['air_conditioning']));

                $reservation = CourtReservation::query()->create([
                    'booking_code' => $code,
                    'court_field_id' => $field->id,
                    'user_id' => $user->id,
                    'sport_id' => $item['sport_id'],
                    'reserved_on' => $start->toDateString(),
                    'starts_at' => $start->format('H:i:s'),
                    'ends_at' => $end->format('H:i:s'),
                    'hours' => (int) $item['hours'],
                    'amount' => (float) $field->price_per_hour * (int) $item['hours'] + $itemsAmount
                        + $surcharges['air_conditioning_amount'] + $surcharges['lighting_amount'],
                    'items_amount' => $itemsAmount,
                    ...$surcharges,
                    'status' => CourtReservation::STATUS_PENDING_PAYMENT,
                ]);
                $reservation->items()->createMany($lines);
                $reservations->push($reservation);
            }

            return $reservations;
        });
    }

    /**
     * @param  array{date: string, start_time: string, hours: int}  $item
     * @return array{Carbon, Carbon}
     */
    private function range(array $item): array
    {
        $start = Carbon::parse($item['date'].' '.$item['start_time']);

        return [$start, $start->copy()->addHours((int) $item['hours'])];
    }

    /**
     * @param  array{sport_id: int, date: string}  $item
     */
    private function assertBookable(CourtField $field, array $item, Carbon $start, Carbon $end, string $key): void
    {
        $label = "{$field->name} {$start->format('H:i')}–{$end->format('H:i')}";

        if (! $field->sports->contains('id', (int) $item['sport_id'])) {
            $this->fail($key.'sport_id', "{$field->name} no ofrece el deporte seleccionado.");
        }

        $opening = Carbon::parse($item['date'].' '.$field->court->opening_time);
        $closing = Carbon::parse($item['date'].' '.$field->court->closing_time);
        if ($start->lt($opening) || $end->gt($closing) || $start->minute !== 0) {
            $this->fail($key.'start_time', "{$label} está fuera del horario de la cancha.");
        }

        if ($start->lte(now())) {
            $this->fail($key.'start_time', "{$label}: ese horario ya pasó.");
        }

        $taken = CourtReservation::query()
            ->where('court_field_id', $field->id)
            ->whereDate('reserved_on', $item['date'])
            ->active()
            ->lockForUpdate()
            ->get()
            ->contains(fn (CourtReservation $existing): bool => $existing->overlaps($start, $end));

        if ($taken) {
            $this->fail($key.'start_time', "{$label} ya está ocupado.");
        }
    }

    /**
     * Gear of the same center and sport, active, and with enough free units for the range
     * (counting other active reservations and the previous ranges of this booking).
     *
     * @param  array{sport_id: int}  $item
     * @param  list<array{item_id: int, start: Carbon, end: Carbon, quantity: int}>  $rented
     */
    private function assertRentable(?RentalItem $rentalItem, CourtField $field, array $item, int $quantity, Carbon $start, Carbon $end, array $rented, string $key): void
    {
        if ($rentalItem === null || $rentalItem->court_id !== $field->court_id || ! $rentalItem->is_active) {
            $this->fail($key.'rental_item_id', 'Ese artículo no se alquila en este centro.');
        }

        if ($rentalItem->sport_id !== (int) $item['sport_id']) {
            $this->fail($key.'rental_item_id', "{$rentalItem->name} no es para el deporte de esta reserva.");
        }

        if ($rentalItem->stock === null) {
            return;
        }

        $inBooking = collect($rented)
            ->filter(fn (array $other): bool => $other['item_id'] === $rentalItem->id && $other['start']->lt($end) && $other['end']->gt($start))
            ->sum('quantity');
        $free = $rentalItem->stock - $rentalItem->reservedQuantity($start, $end) - $inBooking;

        if ($quantity > $free) {
            $this->fail($key.'quantity', $free > 0
                ? "Solo quedan {$free} de {$rentalItem->name} para las {$start->format('H:i')}."
                : "No quedan {$rentalItem->name} para las {$start->format('H:i')}.");
        }
    }

    private function newCode(): string
    {
        do {
            $code = 'B'.Str::upper(Str::random(7));
        } while (CourtReservation::query()->where('booking_code', $code)->exists());

        return $code;
    }

    private function fail(string $key, string $message): never
    {
        throw ValidationException::withMessages([ltrim($key, '.') ?: 'start_time' => $message]);
    }
}
