<?php

namespace Database\Seeders;

use App\Models\Banner;
use App\Models\Court;
use App\Models\Tournament;
use Illuminate\Database\Seeder;

/**
 * Sample home carousel, one banner per kind of link. Idempotent (matched by title).
 * Needs CourtSeeder and TournamentSeeder for the record links; those banners are skipped if missing.
 */
class BannerSeeder extends Seeder
{
    public function run(): void
    {
        $copa = Tournament::query()->where('name', 'Copa Wally Sur 2026')->value('id');
        $relampago = Tournament::query()->where('name', 'Torneo Relámpago Fútbol 5')->value('id');
        $wallySur = Court::query()->where('name', 'Complejo Wally Sur')->value('id');

        $banners = [
            [
                'title' => '¡Reserva tu cancha de Wally hoy!',
                'subtitle' => 'Elige horario y paga con QR en segundos.',
                'button_label' => 'Reservar ahora',
                'background_color' => '#4F46E5',
                'link_type' => 'reserve_courts',
            ],
            [
                'title' => 'Inscríbete al Torneo Relámpago',
                'subtitle' => 'Fútbol 5 · Bs 250 por equipo · cupos limitados',
                'button_label' => 'Inscribir equipo',
                'background_color' => '#7C3AED',
                'link_type' => 'tournament',
                'link_id' => $relampago,
            ],
            [
                'title' => 'Copa Wally Sur 2026 en juego',
                'subtitle' => 'Sigue el fixture y la tabla de posiciones.',
                'button_label' => 'Ver torneo',
                'background_color' => '#D97706',
                'link_type' => 'tournament',
                'link_id' => $copa,
            ],
            [
                'title' => 'Conoce el Complejo Wally Sur',
                'subtitle' => 'Pádel, wally y frontón desde Bs 60 la hora.',
                'button_label' => 'Ver complejo',
                'background_color' => '#16A34A',
                'link_type' => 'court',
                'link_id' => $wallySur,
            ],
            [
                'title' => '¿Celebras algo?',
                'subtitle' => 'Parrilleros y salones en los centros deportivos.',
                'button_label' => 'Ver espacios',
                'background_color' => '#DB2777',
                'link_type' => 'event_spaces',
            ],
            [
                'title' => 'Arma tu equipo',
                'subtitle' => 'Invita a tus amigos y anótense a torneos juntos.',
                'button_label' => 'Mis equipos',
                'background_color' => '#0891B2',
                'link_type' => 'teams',
            ],
        ];

        foreach ($banners as $order => $banner) {
            if (in_array($banner['link_type'], Banner::RECORD_LINKS, true) && empty($banner['link_id'])) {
                continue;
            }

            Banner::query()->updateOrCreate(
                ['title' => $banner['title']],
                [
                    'link_id' => null,
                    'link_url' => null,
                    ...$banner,
                    'sort_order' => ($order + 1) * 10,
                    'is_active' => true,
                ],
            );
        }
    }
}
