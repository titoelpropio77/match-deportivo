<?php

namespace Database\Seeders;

use App\Enums\RatingPolarity;
use App\Models\RatingTag;
use App\Models\Sport;
use Illuminate\Database\Seeder;

class RatingTagSeeder extends Seeder
{
    /**
     * Seed rating tags for each sport.
     */
    public function run(): void
    {
        $negative = [
            ['key' => 'jugo_mal', 'label' => 'Jugó mal', 'marks_absence' => false],
            ['key' => 'mal_comportamiento', 'label' => 'Mal comportamiento', 'marks_absence' => false],
            ['key' => 'no_vino', 'label' => 'No vino a jugar', 'marks_absence' => true],
            ['key' => 'le_falta_mejorar', 'label' => 'Le falta mejorar', 'marks_absence' => false],
        ];

        $positiveBySport = [
            'football_5' => [
                ['key' => 'buen_matador', 'label' => 'Buen matador'],
                ['key' => 'buen_defensor', 'label' => 'Buen defensor'],
                ['key' => 'buen_pase', 'label' => 'Buen pase'],
            ],
            'football_7' => [
                ['key' => 'buen_matador', 'label' => 'Buen matador'],
                ['key' => 'buen_defensor', 'label' => 'Buen defensor'],
                ['key' => 'buen_pase', 'label' => 'Buen pase'],
            ],
            'padel' => [
                ['key' => 'buen_drive', 'label' => 'Buen drive'],
                ['key' => 'buen_reves', 'label' => 'Buen revés'],
                ['key' => 'buen_volea', 'label' => 'Buena volea'],
            ],
            'basketball' => [
                ['key' => 'buen_tirador', 'label' => 'Buen tirador'],
                ['key' => 'buen_reboteador', 'label' => 'Buen reboteador'],
                ['key' => 'buen_pase', 'label' => 'Buen pase'],
            ],
            'tennis' => [
                ['key' => 'buen_saque', 'label' => 'Buen saque'],
                ['key' => 'buen_resto', 'label' => 'Buen resto'],
                ['key' => 'buen_drive', 'label' => 'Buen drive'],
            ],
            'volleyball' => [
                ['key' => 'buen_armador', 'label' => 'Buen armador'],
                ['key' => 'buen_atacante', 'label' => 'Buen atacante'],
                ['key' => 'buen_recepcion', 'label' => 'Buena recepción'],
            ],
            'wallyball' => [
                ['key' => 'buen_armador', 'label' => 'Buen armador'],
                ['key' => 'buen_atacante', 'label' => 'Buen atacante'],
                ['key' => 'buen_recepcion', 'label' => 'Buena recepción'],
            ],
        ];

        foreach ($positiveBySport as $sportKey => $positiveTags) {
            $sport = Sport::query()->where('key', $sportKey)->first();
            if ($sport === null) {
                continue;
            }

            $order = 0;
            foreach ($negative as $tag) {
                RatingTag::updateOrCreate(
                    ['sport_id' => $sport->id, 'key' => $tag['key']],
                    [
                        'label' => $tag['label'],
                        'polarity' => RatingPolarity::Negative,
                        'marks_absence' => $tag['marks_absence'],
                        'sort_order' => $order,
                    ],
                );
                $order++;
            }

            foreach ($positiveTags as $tag) {
                RatingTag::updateOrCreate(
                    ['sport_id' => $sport->id, 'key' => $tag['key']],
                    [
                        'label' => $tag['label'],
                        'polarity' => RatingPolarity::Positive,
                        'marks_absence' => false,
                        'sort_order' => $order,
                    ],
                );
                $order++;
            }
        }
    }
}
