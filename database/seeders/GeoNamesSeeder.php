<?php

namespace Database\Seeders;

use App\Models\Place;
use App\Models\PlaceTranslation;
use Illuminate\Database\Seeder;

class GeoNamesSeeder extends Seeder
{
    /**
     * Seed the places table with major Balkan/Diaspora hubs.
     */
    public function run(): void
    {
        $places = [
            // Vienna, Austria
            [
                'external_id' => '2761369', // GeoNames ID
                'country_code' => 'AT',
                'coordinates' => ['lat' => 48.2085, 'lng' => 16.3721],
                'translations' => [
                    'en' => 'Vienna',
                    'de' => 'Wien',
                    'sr' => 'Beč',
                    'hr' => 'Beč',
                    'bs' => 'Beč',
                ],
            ],
            // Belgrade, Serbia
            [
                'external_id' => '792680', // GeoNames ID
                'country_code' => 'RS',
                'coordinates' => ['lat' => 44.8040, 'lng' => 20.4651],
                'translations' => [
                    'en' => 'Belgrade',
                    'sr' => 'Beograd',
                    'sr-Latn' => 'Beograd',
                    'de' => 'Belgrad',
                    'hu' => 'Belgrád',
                ],
            ],
            // Zurich, Switzerland
            [
                'external_id' => '2657896', // GeoNames ID
                'country_code' => 'CH',
                'coordinates' => ['lat' => 47.3667, 'lng' => 8.5500],
                'translations' => [
                    'en' => 'Zurich',
                    'de' => 'Zürich',
                    'sr' => 'Cirih',
                    'hr' => 'Zürich',
                ],
            ],
            // Sarajevo, Bosnia
            [
                'external_id' => '3191281', // GeoNames ID
                'country_code' => 'BA',
                'coordinates' => ['lat' => 43.8486, 'lng' => 18.3564],
                'translations' => [
                    'en' => 'Sarajevo',
                    'sr' => 'Sarajevo',
                    'bs' => 'Sarajevo',
                    'hr' => 'Sarajevo',
                    'tr' => 'Saraybosna',
                ],
            ],
            // Munich, Germany
            [
                'external_id' => '2867714',
                'country_code' => 'DE',
                'coordinates' => ['lat' => 48.1351, 'lng' => 11.5820],
                'translations' => [
                    'en' => 'Munich',
                    'de' => 'München',
                    'sr' => 'Minhen',
                    'hr' => 'München',
                ],
            ],
        ];

        foreach ($places as $data) {
            $place = Place::firstOrCreate(
                ['external_id' => $data['external_id']],
                [
                    'source' => 'geonames',
                    'type' => 'city',
                    'country_code' => $data['country_code'],
                    'coordinates' => $data['coordinates'],
                ]
            );

            foreach ($data['translations'] as $locale => $name) {
                PlaceTranslation::updateOrCreate(
                    [
                        'place_id' => $place->id,
                        'locale' => $locale,
                    ],
                    ['name' => $name]
                );
            }
        }

        $this->command->info('Seeded ' . count($places) . ' major cities with translations.');
    }
}
