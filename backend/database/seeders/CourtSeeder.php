<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\Court;
use App\Models\CourtReservation;
use App\Models\EventAmenity;
use App\Models\Sport;
use App\Models\User;
use Illuminate\Database\Seeder;

class CourtSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $courts = [
            [
                'name' => 'Complejo Wally Sur',
                'address' => 'Av. Santos Dumont 3er anillo, Santa Cruz',
                'latitude' => -17.783300,
                'longitude' => -63.182100,
                'opening_time' => '08:00',
                'closing_time' => '23:00',
                'review_data' => ['average' => 4.8, 'count' => 32],
                'sports' => ['wallyball', 'fronton', 'padel'],
                'fields' => [
                    ['name' => 'Cancha 1', 'price_per_hour' => 60, 'sports' => ['wallyball', 'fronton', 'padel']],
                    ['name' => 'Cancha 2', 'price_per_hour' => 60, 'sports' => ['wallyball', 'fronton', 'padel']],
                    ['name' => 'Cancha 3', 'price_per_hour' => 60, 'sports' => ['wallyball', 'fronton', 'padel']],
                ],
                'photos' => [
                    'https://picsum.photos/seed/wally-sur-1/600/400',
                    'https://picsum.photos/seed/wally-sur-2/600/400',
                ],
                'rental_items' => [
                    ['sport' => 'wallyball', 'name' => 'Pelota de wally', 'price' => 10, 'price_type' => 'flat', 'stock' => 6],
                    ['sport' => 'padel', 'name' => 'Raqueta de pádel', 'price' => 15, 'price_type' => 'per_hour', 'stock' => 8],
                    ['sport' => 'padel', 'name' => 'Tubo de pelotas de pádel (x3)', 'price' => 25, 'price_type' => 'flat', 'stock' => null],
                    ['sport' => 'fronton', 'name' => 'Paleta de frontón', 'price' => 10, 'price_type' => 'per_hour', 'stock' => 4],
                ],
                'event_spaces' => [
                    [
                        'name' => 'Parrillero La Brasa',
                        'type' => 'grill',
                        'description' => 'Parrillero techado junto a las canchas, ideal para el asado después del partido.',
                        'price_per_hour' => 80,
                        'capacity' => 25,
                        'min_hours' => 3,
                        'amenities' => ['grill', 'tables_chairs', 'restrooms', 'fridge', 'covered', 'lighting'],
                        'rules' => "Traer carbón y utensilios propios.\nMúsica a volumen moderado hasta las 23:00.\nEntregar el espacio limpio.",
                        'photo_path' => 'https://picsum.photos/seed/parrillero-brasa/800/500',
                    ],
                    [
                        'name' => 'Salón Mirador',
                        'type' => 'hall',
                        'description' => 'Salón climatizado con vista a las canchas para cumpleaños, reuniones y eventos de empresa.',
                        'price_per_hour' => 150,
                        'capacity' => 60,
                        'min_hours' => 2,
                        'amenities' => ['tables_chairs', 'restrooms', 'kitchen', 'sound', 'air_conditioning', 'wifi', 'parking'],
                        'rules' => 'Decoración sin clavos ni cinta en las paredes.',
                        'photo_path' => 'https://picsum.photos/seed/salon-mirador/800/500',
                    ],
                ],
            ],
            [
                'name' => 'Canchas El Torneo',
                'address' => 'Calle Beni y 4to anillo, Equipetrol',
                'latitude' => -17.766900,
                'longitude' => -63.192400,
                'opening_time' => '09:00',
                'closing_time' => '22:00',
                'review_data' => ['average' => 4.5, 'count' => 18],
                'sports' => ['football_5', 'football_7'],
                'fields' => [
                    ['name' => 'Cancha A', 'price_per_hour' => 80, 'sports' => ['football_5', 'football_7']],
                ],
                'photos' => [
                    'https://picsum.photos/seed/el-torneo-1/600/400',
                ],
                'rental_items' => [
                    ['sport' => 'football_5', 'name' => 'Pelota de fútbol Nº 4', 'price' => 10, 'price_type' => 'flat', 'stock' => 4],
                    ['sport' => 'football_7', 'name' => 'Pelota de fútbol Nº 5', 'price' => 10, 'price_type' => 'flat', 'stock' => 4],
                    ['sport' => 'football_7', 'name' => 'Juego de pecheras (x7)', 'price' => 15, 'price_type' => 'flat', 'stock' => 2],
                ],
            ],
            [
                'name' => 'Wally Center Equipetrol',
                'address' => 'Av. San Martín, Equipetrol',
                'latitude' => -17.769800,
                'longitude' => -63.190500,
                'opening_time' => '07:00',
                'closing_time' => '23:30',
                'review_data' => ['average' => 4.6, 'count' => 27],
                'sports' => ['padel', 'tennis'],
                'fields' => [
                    ['name' => 'Cancha Pádel', 'price_per_hour' => 70, 'sports' => ['padel', 'tennis']],
                ],
                'photos' => [
                    'https://picsum.photos/seed/wally-center-1/600/400',
                    'https://picsum.photos/seed/wally-center-2/600/400',
                ],
                'rental_items' => [
                    ['sport' => 'padel', 'name' => 'Raqueta de pádel', 'price' => 20, 'price_type' => 'per_hour', 'stock' => 6],
                    ['sport' => 'tennis', 'name' => 'Raqueta de tenis', 'price' => 20, 'price_type' => 'per_hour', 'stock' => 4],
                ],
            ],
            [
                'name' => 'Arena Norte',
                'address' => 'Zona Norte, 3er anillo externo, Santa Cruz',
                'latitude' => -17.740300,
                'longitude' => -63.180700,
                'opening_time' => '08:00',
                'closing_time' => '22:00',
                'review_data' => ['average' => 4.3, 'count' => 11],
                'sports' => ['basketball', 'football_5'],
                'fields' => [
                    ['name' => 'Cancha 1', 'price_per_hour' => 50, 'sports' => ['basketball', 'football_5']],
                    ['name' => 'Cancha 2', 'price_per_hour' => 50, 'sports' => ['basketball', 'football_5']],
                ],
                'photos' => [
                    'https://picsum.photos/seed/arena-norte-1/600/400',
                ],
                'rental_items' => [
                    ['sport' => 'basketball', 'name' => 'Pelota de básquet', 'price' => 10, 'price_type' => 'flat', 'stock' => 3],
                ],
                'event_spaces' => [
                    [
                        'name' => 'Quincho del Norte',
                        'type' => 'quincho',
                        'description' => 'Quincho con churrasquera y área verde para niños.',
                        'price_per_hour' => 60,
                        'capacity' => 30,
                        'min_hours' => 3,
                        'amenities' => ['grill', 'tables_chairs', 'restrooms', 'kids_area', 'covered'],
                        'rules' => null,
                        'photo_path' => 'https://picsum.photos/seed/quincho-norte/800/500',
                    ],
                ],
            ],
        ];

        // All sample venues are in Santa Cruz de la Sierra (city rows come from the cities migration).
        $santaCruzId = City::query()->where('key', 'santa_cruz_de_la_sierra')->value('id');

        foreach ($courts as $data) {
            $court = Court::updateOrCreate(
                ['name' => $data['name']],
                [
                    'city_id' => $santaCruzId,
                    'address' => $data['address'],
                    'latitude' => $data['latitude'],
                    'longitude' => $data['longitude'],
                    'opening_time' => $data['opening_time'],
                    'closing_time' => $data['closing_time'],
                    'review_data' => $data['review_data'],
                ]
            );

            $sportIds = Sport::query()->whereIn('key', $data['sports'])->pluck('id');
            $court->sports()->sync($sportIds);

            $court->photos()->delete();
            foreach ($data['photos'] as $index => $url) {
                $court->photos()->create(['url' => $url, 'order' => $index]);
            }

            $court->fields()->delete();
            foreach ($data['fields'] as $fieldData) {
                $field = $court->fields()->create([
                    'name' => $fieldData['name'],
                    'price_per_hour' => $fieldData['price_per_hour'],
                ]);
                $fieldSportIds = Sport::query()->whereIn('key', $fieldData['sports'])->pluck('id');
                $field->sports()->sync($fieldSportIds);
            }

            // Upsert by name and sport so rented gear in existing reservations keeps its link.
            foreach ($data['rental_items'] ?? [] as $itemData) {
                $sportId = Sport::query()->where('key', $itemData['sport'])->value('id');
                if ($sportId === null) {
                    continue;
                }
                $court->rentalItems()->updateOrCreate(
                    ['name' => $itemData['name'], 'sport_id' => $sportId],
                    collect($itemData)->except('sport')->all(),
                );
            }

            // Upsert by name so existing event reservations are kept.
            foreach ($data['event_spaces'] ?? [] as $spaceData) {
                $space = $court->eventSpaces()->updateOrCreate(['name' => $spaceData['name']], collect($spaceData)->except('amenities')->all());
                $space->amenities()->sync(EventAmenity::query()->whereIn('key', $spaceData['amenities'] ?? [])->pluck('id'));
            }
        }

    }

    /**
     * One occupied hour so the calendar shows a slot that cannot be selected.
     */
    public function seedSampleReservation(): void
    {
        $user = User::query()->where('email', 'carlos.mamani@example.com')->first();
        $field = Court::query()->where('name', 'Complejo Wally Sur')->first()
            ?->fields()
            ->where('name', 'Cancha 1')
            ->first();
        $sportId = Sport::query()->where('key', 'wallyball')->value('id');

        if ($user === null || $field === null || $sportId === null) {
            return;
        }

        CourtReservation::query()->updateOrCreate(
            [
                'court_field_id' => $field->id,
                'reserved_on' => now()->toDateString(),
                'starts_at' => '19:00:00',
            ],
            [
                'user_id' => $user->id,
                'sport_id' => $sportId,
                'ends_at' => '20:00:00',
                'hours' => 1,
                'amount' => 60,
                'status' => CourtReservation::STATUS_PAID,
                'payment_method' => 'qr',
                'paid_at' => now(),
            ]
        );
    }
}
