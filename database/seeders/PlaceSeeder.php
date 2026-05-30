<?php

namespace Database\Seeders;

use App\Models\Place;
use App\Models\PlaceTranslation;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PlaceSeeder extends Seeder
{
    /**
     * Seed all Balkan + diaspora cities.
     * Source: GeoNames cities with population > 5000 for the region.
     */
    public function run(): void
    {
        $places = $this->getPlaces();
        $count = 0;

        foreach ($places as $placeData) {
            $existing = Place::where('external_id', $placeData['geonames_id'])->first();
            if ($existing) {
                // Update translations if missing
                foreach ($placeData['translations'] as $locale => $name) {
                    PlaceTranslation::firstOrCreate(
                    ['place_id' => $existing->id, 'locale' => $locale],
                    ['name' => $name]
                    );
                }
                continue;
            }

            $place = Place::create([
                'external_id' => $placeData['geonames_id'],
                'source' => 'geonames',
                'type' => 'city',
                'country_code' => $placeData['country_code'],
                'coordinates' => $placeData['coordinates'] ?? null,
            ]);

            foreach ($placeData['translations'] as $locale => $name) {
                PlaceTranslation::create([
                    'place_id' => $place->id,
                    'locale' => $locale,
                    'name' => $name,
                ]);
            }

            $count++;
        }

        $this->command->info("Places seeded: {$count} new, " . Place::count() . " total.");
    }

    private function getPlaces(): array
    {
        return [
            // =============================================
            // SERBIA (RS)
            // =============================================
            ['geonames_id' => '792680', 'country_code' => 'RS', 'coordinates' => ['lat' => 44.804, 'lng' => 20.465],
                'translations' => ['en' => 'Belgrade', 'sr' => 'Beograd', 'sr-Latn' => 'Beograd', 'de' => 'Belgrad']],
            ['geonames_id' => '789128', 'country_code' => 'RS', 'coordinates' => ['lat' => 43.321, 'lng' => 21.896],
                'translations' => ['en' => 'Niš', 'sr' => 'Ниш', 'sr-Latn' => 'Niš', 'de' => 'Nisch']],
            ['geonames_id' => '789518', 'country_code' => 'RS', 'coordinates' => ['lat' => 45.252, 'lng' => 19.837],
                'translations' => ['en' => 'Novi Sad', 'sr' => 'Нови Сад', 'sr-Latn' => 'Novi Sad', 'de' => 'Neusatz']],
            ['geonames_id' => '788135', 'country_code' => 'RS', 'coordinates' => ['lat' => 43.727, 'lng' => 20.688],
                'translations' => ['en' => 'Kragujevac', 'sr' => 'Крагујевац', 'sr-Latn' => 'Kragujevac', 'de' => 'Kragujevac']],
            ['geonames_id' => '791223', 'country_code' => 'RS', 'coordinates' => ['lat' => 45.390, 'lng' => 20.389],
                'translations' => ['en' => 'Zrenjanin', 'sr' => 'Зрењанин', 'sr-Latn' => 'Zrenjanin', 'de' => 'Zrenjanin']],
            ['geonames_id' => '3204541', 'country_code' => 'RS', 'coordinates' => ['lat' => 44.873, 'lng' => 20.640],
                'translations' => ['en' => 'Pančevo', 'sr' => 'Панчево', 'sr-Latn' => 'Pančevo', 'de' => 'Pantschowa']],
            ['geonames_id' => '785358', 'country_code' => 'RS', 'coordinates' => ['lat' => 43.857, 'lng' => 19.846],
                'translations' => ['en' => 'Čačak', 'sr' => 'Чачак', 'sr-Latn' => 'Čačak', 'de' => 'Tschatschak']],
            ['geonames_id' => '787753', 'country_code' => 'RS', 'coordinates' => ['lat' => 43.886, 'lng' => 20.347],
                'translations' => ['en' => 'Kraljevo', 'sr' => 'Краљево', 'sr-Latn' => 'Kraljevo', 'de' => 'Kraljevo']],
            ['geonames_id' => '789398', 'country_code' => 'RS', 'coordinates' => ['lat' => 43.141, 'lng' => 20.519],
                'translations' => ['en' => 'Novi Pazar', 'sr' => 'Нови Пазар', 'sr-Latn' => 'Novi Pazar', 'de' => 'Novi Pazar']],
            ['geonames_id' => '786714', 'country_code' => 'RS', 'coordinates' => ['lat' => 43.586, 'lng' => 21.334],
                'translations' => ['en' => 'Kruševac', 'sr' => 'Крушевац', 'sr-Latn' => 'Kruševac', 'de' => 'Kruschewatz']],
            ['geonames_id' => '783814', 'country_code' => 'RS', 'coordinates' => ['lat' => 43.720, 'lng' => 20.456],
                'translations' => ['en' => 'Gornji Milanovac', 'sr' => 'Горњи Милановац', 'sr-Latn' => 'Gornji Milanovac', 'de' => 'Gornji Milanovac']],
            ['geonames_id' => '790274', 'country_code' => 'RS', 'coordinates' => ['lat' => 45.775, 'lng' => 19.113],
                'translations' => ['en' => 'Subotica', 'sr' => 'Суботица', 'sr-Latn' => 'Subotica', 'de' => 'Subotica']],
            ['geonames_id' => '789402', 'country_code' => 'RS', 'coordinates' => ['lat' => 43.970, 'lng' => 21.298],
                'translations' => ['en' => 'Jagodina', 'sr' => 'Јагодина', 'sr-Latn' => 'Jagodina']],
            ['geonames_id' => '790588', 'country_code' => 'RS', 'coordinates' => ['lat' => 44.263, 'lng' => 19.882],
                'translations' => ['en' => 'Valjevo', 'sr' => 'Ваљево', 'sr-Latn' => 'Valjevo']],
            ['geonames_id' => '785842', 'country_code' => 'RS', 'coordinates' => ['lat' => 43.137, 'lng' => 22.183],
                'translations' => ['en' => 'Leskovac', 'sr' => 'Лесковац', 'sr-Latn' => 'Leskovac']],
            ['geonames_id' => '788613', 'country_code' => 'RS', 'coordinates' => ['lat' => 44.361, 'lng' => 20.932],
                'translations' => ['en' => 'Smederevo', 'sr' => 'Смедерево', 'sr-Latn' => 'Smederevo']],
            ['geonames_id' => '787657', 'country_code' => 'RS', 'coordinates' => ['lat' => 43.990, 'lng' => 20.918],
                'translations' => ['en' => 'Požarevac', 'sr' => 'Пожаревац', 'sr-Latn' => 'Požarevac']],
            ['geonames_id' => '792439', 'country_code' => 'RS', 'coordinates' => ['lat' => 43.848, 'lng' => 19.287],
                'translations' => ['en' => 'Užice', 'sr' => 'Ужице', 'sr-Latn' => 'Užice']],
            ['geonames_id' => '789337', 'country_code' => 'RS', 'coordinates' => ['lat' => 44.632, 'lng' => 20.687],
                'translations' => ['en' => 'Obrenovac', 'sr' => 'Обреновац', 'sr-Latn' => 'Obrenovac']],
            ['geonames_id' => '789610', 'country_code' => 'RS', 'coordinates' => ['lat' => 44.317, 'lng' => 19.549],
                'translations' => ['en' => 'Loznica', 'sr' => 'Лозница', 'sr-Latn' => 'Loznica']],
            ['geonames_id' => '785254', 'country_code' => 'RS', 'coordinates' => ['lat' => 43.296, 'lng' => 20.041],
                'translations' => ['en' => 'Prijepolje', 'sr' => 'Пријепоље', 'sr-Latn' => 'Prijepolje']],
            ['geonames_id' => '786100', 'country_code' => 'RS', 'coordinates' => ['lat' => 43.163, 'lng' => 22.586],
                'translations' => ['en' => 'Pirot', 'sr' => 'Пирот', 'sr-Latn' => 'Pirot']],
            ['geonames_id' => '790063', 'country_code' => 'RS', 'coordinates' => ['lat' => 44.769, 'lng' => 20.412],
                'translations' => ['en' => 'Zemun', 'sr' => 'Земун', 'sr-Latn' => 'Zemun']],
            ['geonames_id' => '790413', 'country_code' => 'RS', 'coordinates' => ['lat' => 44.773, 'lng' => 20.476],
                'translations' => ['en' => 'Novi Beograd', 'sr' => 'Нови Београд', 'sr-Latn' => 'Novi Beograd']],
            ['geonames_id' => '786717', 'country_code' => 'RS', 'coordinates' => ['lat' => 44.018, 'lng' => 20.916],
                'translations' => ['en' => 'Aranđelovac', 'sr' => 'Аранђеловац', 'sr-Latn' => 'Aranđelovac']],
            ['geonames_id' => '785762', 'country_code' => 'RS', 'coordinates' => ['lat' => 44.219, 'lng' => 20.421],
                'translations' => ['en' => 'Ljig', 'sr' => 'Љиг', 'sr-Latn' => 'Ljig']],
            ['geonames_id' => '790411', 'country_code' => 'RS', 'coordinates' => ['lat' => 45.463, 'lng' => 19.231],
                'translations' => ['en' => 'Sombor', 'sr' => 'Сомбор', 'sr-Latn' => 'Sombor']],
            ['geonames_id' => '786009', 'country_code' => 'RS', 'coordinates' => ['lat' => 44.753, 'lng' => 19.692],
                'translations' => ['en' => 'Šabac', 'sr' => 'Шабац', 'sr-Latn' => 'Šabac']],
            ['geonames_id' => '790325', 'country_code' => 'RS', 'coordinates' => ['lat' => 43.985, 'lng' => 21.408],
                'translations' => ['en' => 'Paraćin', 'sr' => 'Параћин', 'sr-Latn' => 'Paraćin']],
            ['geonames_id' => '785609', 'country_code' => 'RS', 'coordinates' => ['lat' => 42.974, 'lng' => 21.944],
                'translations' => ['en' => 'Vranje', 'sr' => 'Врање', 'sr-Latn' => 'Vranje']],
            ['geonames_id' => '791949', 'country_code' => 'RS', 'coordinates' => ['lat' => 43.461, 'lng' => 21.263],
                'translations' => ['en' => 'Trstenik', 'sr' => 'Трстеник', 'sr-Latn' => 'Trstenik']],
            ['geonames_id' => '787074', 'country_code' => 'RS', 'coordinates' => ['lat' => 46.069, 'lng' => 19.671],
                'translations' => ['en' => 'Kikinda', 'sr' => 'Кикинда', 'sr-Latn' => 'Kikinda']],
            ['geonames_id' => '790017', 'country_code' => 'RS', 'coordinates' => ['lat' => 44.017, 'lng' => 21.556],
                'translations' => ['en' => 'Ćuprija', 'sr' => 'Ћуприја', 'sr-Latn' => 'Ćuprija']],
            ['geonames_id' => '791655', 'country_code' => 'RS', 'coordinates' => ['lat' => 43.978, 'lng' => 21.956],
                'translations' => ['en' => 'Zaječar', 'sr' => 'Зајечар', 'sr-Latn' => 'Zaječar']],
            ['geonames_id' => '789621', 'country_code' => 'RS', 'coordinates' => ['lat' => 43.584, 'lng' => 20.676],
                'translations' => ['en' => 'Trstenik', 'sr' => 'Trstenik']],
            ['geonames_id' => '3204551', 'country_code' => 'RS', 'coordinates' => ['lat' => 44.016, 'lng' => 21.264],
                'translations' => ['en' => 'Svilajnac', 'sr' => 'Свилајнац', 'sr-Latn' => 'Svilajnac']],

            // =============================================
            // BOSNIA AND HERZEGOVINA (BA)
            // =============================================
            ['geonames_id' => '3191281', 'country_code' => 'BA', 'coordinates' => ['lat' => 43.85, 'lng' => 18.383],
                'translations' => ['en' => 'Sarajevo', 'sr' => 'Сарајево', 'sr-Latn' => 'Sarajevo', 'bs' => 'Sarajevo']],
            ['geonames_id' => '3186573', 'country_code' => 'BA', 'coordinates' => ['lat' => 44.341, 'lng' => 17.662],
                'translations' => ['en' => 'Zenica', 'sr' => 'Зеница', 'sr-Latn' => 'Zenica', 'bs' => 'Zenica']],
            ['geonames_id' => '3194099', 'country_code' => 'BA', 'coordinates' => ['lat' => 43.342, 'lng' => 17.808],
                'translations' => ['en' => 'Mostar', 'sr' => 'Мостар', 'sr-Latn' => 'Mostar', 'bs' => 'Mostar']],
            ['geonames_id' => '3188582', 'country_code' => 'BA', 'coordinates' => ['lat' => 44.537, 'lng' => 18.673],
                'translations' => ['en' => 'Tuzla', 'sr' => 'Тузла', 'sr-Latn' => 'Tuzla', 'bs' => 'Tuzla']],
            ['geonames_id' => '3191880', 'country_code' => 'BA', 'coordinates' => ['lat' => 44.775, 'lng' => 17.186],
                'translations' => ['en' => 'Banja Luka', 'sr' => 'Бања Лука', 'sr-Latn' => 'Banja Luka', 'bs' => 'Banja Luka']],
            ['geonames_id' => '3189962', 'country_code' => 'BA', 'coordinates' => ['lat' => 43.837, 'lng' => 18.313],
                'translations' => ['en' => 'Ilidža', 'sr' => 'Илиџа', 'sr-Latn' => 'Ilidža', 'bs' => 'Ilidža']],
            ['geonames_id' => '3189096', 'country_code' => 'BA', 'coordinates' => ['lat' => 44.768, 'lng' => 17.192],
                'translations' => ['en' => 'Prijedor', 'sr' => 'Приједор', 'sr-Latn' => 'Prijedor', 'bs' => 'Prijedor']],
            ['geonames_id' => '3193798', 'country_code' => 'BA', 'coordinates' => ['lat' => 44.868, 'lng' => 18.810],
                'translations' => ['en' => 'Brčko', 'sr' => 'Брчко', 'sr-Latn' => 'Brčko', 'bs' => 'Brčko']],
            ['geonames_id' => '3192243', 'country_code' => 'BA', 'coordinates' => ['lat' => 44.209, 'lng' => 17.908],
                'translations' => ['en' => 'Travnik', 'sr' => 'Травник', 'sr-Latn' => 'Travnik', 'bs' => 'Travnik']],
            ['geonames_id' => '3188974', 'country_code' => 'BA', 'coordinates' => ['lat' => 43.527, 'lng' => 18.667],
                'translations' => ['en' => 'Goražde', 'sr' => 'Горажде', 'sr-Latn' => 'Goražde', 'bs' => 'Goražde']],
            ['geonames_id' => '3193935', 'country_code' => 'BA', 'coordinates' => ['lat' => 44.461, 'lng' => 16.715],
                'translations' => ['en' => 'Bihać', 'sr' => 'Бихаћ', 'sr-Latn' => 'Bihać', 'bs' => 'Bihać']],
            ['geonames_id' => '3193397', 'country_code' => 'BA', 'coordinates' => ['lat' => 44.756, 'lng' => 18.334],
                'translations' => ['en' => 'Doboj', 'sr' => 'Добој', 'sr-Latn' => 'Doboj', 'bs' => 'Doboj']],
            ['geonames_id' => '3187658', 'country_code' => 'BA', 'coordinates' => ['lat' => 43.833, 'lng' => 18.433],
                'translations' => ['en' => 'Vogošća', 'sr' => 'Вогошћа', 'sr-Latn' => 'Vogošća', 'bs' => 'Vogošća']],

            // =============================================
            // CROATIA (HR)
            // =============================================
            ['geonames_id' => '3186886', 'country_code' => 'HR', 'coordinates' => ['lat' => 45.814, 'lng' => 15.978],
                'translations' => ['en' => 'Zagreb', 'sr' => 'Загреб', 'sr-Latn' => 'Zagreb', 'hr' => 'Zagreb', 'de' => 'Agram']],
            ['geonames_id' => '3190261', 'country_code' => 'HR', 'coordinates' => ['lat' => 43.509, 'lng' => 16.440],
                'translations' => ['en' => 'Split', 'sr' => 'Сплит', 'sr-Latn' => 'Split', 'hr' => 'Split']],
            ['geonames_id' => '3193935', 'country_code' => 'HR', 'coordinates' => ['lat' => 45.333, 'lng' => 14.442],
                'translations' => ['en' => 'Rijeka', 'sr' => 'Ријека', 'sr-Latn' => 'Rijeka', 'hr' => 'Rijeka']],
            ['geonames_id' => '3192224', 'country_code' => 'HR', 'coordinates' => ['lat' => 45.553, 'lng' => 18.697],
                'translations' => ['en' => 'Osijek', 'sr' => 'Осијек', 'sr-Latn' => 'Osijek', 'hr' => 'Osijek']],
            ['geonames_id' => '3186952', 'country_code' => 'HR', 'coordinates' => ['lat' => 44.119, 'lng' => 15.232],
                'translations' => ['en' => 'Zadar', 'sr' => 'Задар', 'sr-Latn' => 'Zadar', 'hr' => 'Zadar']],
            ['geonames_id' => '3194567', 'country_code' => 'HR', 'coordinates' => ['lat' => 42.641, 'lng' => 18.111],
                'translations' => ['en' => 'Dubrovnik', 'sr' => 'Дубровник', 'sr-Latn' => 'Dubrovnik', 'hr' => 'Dubrovnik']],

            // =============================================
            // MONTENEGRO (ME)
            // =============================================
            ['geonames_id' => '3193044', 'country_code' => 'ME', 'coordinates' => ['lat' => 42.441, 'lng' => 19.263],
                'translations' => ['en' => 'Podgorica', 'sr' => 'Подгорица', 'sr-Latn' => 'Podgorica', 'cnr' => 'Podgorica']],
            ['geonames_id' => '3194380', 'country_code' => 'ME', 'coordinates' => ['lat' => 42.770, 'lng' => 19.285],
                'translations' => ['en' => 'Nikšić', 'sr' => 'Никшић', 'sr-Latn' => 'Nikšić', 'cnr' => 'Nikšić']],
            ['geonames_id' => '3192702', 'country_code' => 'ME', 'coordinates' => ['lat' => 42.286, 'lng' => 18.841],
                'translations' => ['en' => 'Budva', 'sr' => 'Будва', 'sr-Latn' => 'Budva', 'cnr' => 'Budva']],
            ['geonames_id' => '3191062', 'country_code' => 'ME', 'coordinates' => ['lat' => 42.098, 'lng' => 19.101],
                'translations' => ['en' => 'Bar', 'sr' => 'Бар', 'sr-Latn' => 'Bar', 'cnr' => 'Bar']],
            ['geonames_id' => '3194099', 'country_code' => 'ME', 'coordinates' => ['lat' => 42.452, 'lng' => 18.531],
                'translations' => ['en' => 'Herceg Novi', 'sr' => 'Херцег Нови', 'sr-Latn' => 'Herceg Novi', 'cnr' => 'Herceg Novi']],

            // =============================================
            // NORTH MACEDONIA (MK)
            // =============================================
            ['geonames_id' => '785842', 'country_code' => 'MK', 'coordinates' => ['lat' => 41.997, 'lng' => 21.431],
                'translations' => ['en' => 'Skopje', 'sr' => 'Скопље', 'sr-Latn' => 'Skoplje', 'mk' => 'Скопје']],
            ['geonames_id' => '792578', 'country_code' => 'MK', 'coordinates' => ['lat' => 41.031, 'lng' => 21.334],
                'translations' => ['en' => 'Bitola', 'sr' => 'Битољ', 'sr-Latn' => 'Bitolj', 'mk' => 'Битола']],
            ['geonames_id' => '863903', 'country_code' => 'MK', 'coordinates' => ['lat' => 41.116, 'lng' => 20.800],
                'translations' => ['en' => 'Ohrid', 'sr' => 'Охрид', 'sr-Latn' => 'Ohrid', 'mk' => 'Охрид']],

            // =============================================
            // SLOVENIA (SI)
            // =============================================
            ['geonames_id' => '3196359', 'country_code' => 'SI', 'coordinates' => ['lat' => 46.056, 'lng' => 14.508],
                'translations' => ['en' => 'Ljubljana', 'sr' => 'Љубљана', 'sr-Latn' => 'Ljubljana', 'sl' => 'Ljubljana', 'de' => 'Laibach']],
            ['geonames_id' => '3192366', 'country_code' => 'SI', 'coordinates' => ['lat' => 46.554, 'lng' => 15.646],
                'translations' => ['en' => 'Maribor', 'sr' => 'Марибор', 'sr-Latn' => 'Maribor', 'sl' => 'Maribor']],

            // =============================================
            // AUSTRIA (AT) - Major diaspora destination
            // =============================================
            ['geonames_id' => '2761369', 'country_code' => 'AT', 'coordinates' => ['lat' => 48.209, 'lng' => 16.373],
                'translations' => ['en' => 'Vienna', 'de' => 'Wien', 'sr' => 'Беч', 'sr-Latn' => 'Beč', 'hr' => 'Beč', 'bs' => 'Beč']],
            ['geonames_id' => '2772400', 'country_code' => 'AT', 'coordinates' => ['lat' => 48.306, 'lng' => 14.286],
                'translations' => ['en' => 'Linz', 'de' => 'Linz', 'sr' => 'Линц', 'sr-Latn' => 'Linc']],
            ['geonames_id' => '2766824', 'country_code' => 'AT', 'coordinates' => ['lat' => 47.811, 'lng' => 13.055],
                'translations' => ['en' => 'Salzburg', 'de' => 'Salzburg', 'sr' => 'Салцбург', 'sr-Latn' => 'Salcburg']],
            ['geonames_id' => '2778067', 'country_code' => 'AT', 'coordinates' => ['lat' => 47.070, 'lng' => 15.439],
                'translations' => ['en' => 'Graz', 'de' => 'Graz', 'sr' => 'Грац', 'sr-Latn' => 'Grac']],
            ['geonames_id' => '2775220', 'country_code' => 'AT', 'coordinates' => ['lat' => 47.263, 'lng' => 11.394],
                'translations' => ['en' => 'Innsbruck', 'de' => 'Innsbruck', 'sr' => 'Инсбрук', 'sr-Latn' => 'Insbrug']],
            ['geonames_id' => '2762518', 'country_code' => 'AT', 'coordinates' => ['lat' => 46.625, 'lng' => 14.308],
                'translations' => ['en' => 'Klagenfurt', 'de' => 'Klagenfurt', 'sr' => 'Клагенфурт', 'sr-Latn' => 'Klagenfurt']],
            ['geonames_id' => '2764939', 'country_code' => 'AT', 'coordinates' => ['lat' => 48.205, 'lng' => 15.624],
                'translations' => ['en' => 'St. Pölten', 'de' => 'St. Pölten', 'sr' => 'Санкт Полтен', 'sr-Latn' => 'Sankt Polten']],
            ['geonames_id' => '2761524', 'country_code' => 'AT', 'coordinates' => ['lat' => 47.482, 'lng' => 14.782],
                'translations' => ['en' => 'Wels', 'de' => 'Wels', 'sr' => 'Велс', 'sr-Latn' => 'Vels']],

            // =============================================
            // GERMANY (DE) - Major diaspora destination
            // =============================================
            ['geonames_id' => '2867714', 'country_code' => 'DE', 'coordinates' => ['lat' => 48.137, 'lng' => 11.576],
                'translations' => ['en' => 'Munich', 'de' => 'München', 'sr' => 'Минхен', 'sr-Latn' => 'Minhen']],
            ['geonames_id' => '2950157', 'country_code' => 'DE', 'coordinates' => ['lat' => 52.520, 'lng' => 13.405],
                'translations' => ['en' => 'Berlin', 'de' => 'Berlin', 'sr' => 'Берлин', 'sr-Latn' => 'Berlin']],
            ['geonames_id' => '2925533', 'country_code' => 'DE', 'coordinates' => ['lat' => 50.111, 'lng' => 8.682],
                'translations' => ['en' => 'Frankfurt', 'de' => 'Frankfurt am Main', 'sr' => 'Франкфурт', 'sr-Latn' => 'Frankfurt']],
            ['geonames_id' => '2953533', 'country_code' => 'DE', 'coordinates' => ['lat' => 48.776, 'lng' => 9.177],
                'translations' => ['en' => 'Stuttgart', 'de' => 'Stuttgart', 'sr' => 'Штутгарт', 'sr-Latn' => 'Štutgart']],
            ['geonames_id' => '2886242', 'country_code' => 'DE', 'coordinates' => ['lat' => 50.938, 'lng' => 6.957],
                'translations' => ['en' => 'Cologne', 'de' => 'Köln', 'sr' => 'Келн', 'sr-Latn' => 'Keln']],
            ['geonames_id' => '2911298', 'country_code' => 'DE', 'coordinates' => ['lat' => 53.551, 'lng' => 9.994],
                'translations' => ['en' => 'Hamburg', 'de' => 'Hamburg', 'sr' => 'Хамбург', 'sr-Latn' => 'Hamburg']],
            ['geonames_id' => '2935022', 'country_code' => 'DE', 'coordinates' => ['lat' => 51.514, 'lng' => 7.468],
                'translations' => ['en' => 'Dortmund', 'de' => 'Dortmund', 'sr' => 'Дортмунд', 'sr-Latn' => 'Dortmund']],
            ['geonames_id' => '2934691', 'country_code' => 'DE', 'coordinates' => ['lat' => 51.233, 'lng' => 6.783],
                'translations' => ['en' => 'Düsseldorf', 'de' => 'Düsseldorf', 'sr' => 'Диселдорф', 'sr-Latn' => 'Diseldorf']],
            ['geonames_id' => '2861650', 'country_code' => 'DE', 'coordinates' => ['lat' => 49.452, 'lng' => 11.077],
                'translations' => ['en' => 'Nuremberg', 'de' => 'Nürnberg', 'sr' => 'Нирнберг', 'sr-Latn' => 'Nirnberg']],

            // =============================================
            // SWITZERLAND (CH)
            // =============================================
            ['geonames_id' => '2657896', 'country_code' => 'CH', 'coordinates' => ['lat' => 47.367, 'lng' => 8.550],
                'translations' => ['en' => 'Zurich', 'de' => 'Zürich', 'sr' => 'Цирих', 'sr-Latn' => 'Cirih']],
            ['geonames_id' => '2660646', 'country_code' => 'CH', 'coordinates' => ['lat' => 46.948, 'lng' => 7.448],
                'translations' => ['en' => 'Bern', 'de' => 'Bern', 'sr' => 'Берн', 'sr-Latn' => 'Bern']],
            ['geonames_id' => '2659811', 'country_code' => 'CH', 'coordinates' => ['lat' => 46.200, 'lng' => 6.150],
                'translations' => ['en' => 'Geneva', 'de' => 'Genf', 'sr' => 'Женева', 'sr-Latn' => 'Ženeva', 'fr' => 'Genève']],
            ['geonames_id' => '2661604', 'country_code' => 'CH', 'coordinates' => ['lat' => 47.559, 'lng' => 7.589],
                'translations' => ['en' => 'Basel', 'de' => 'Basel', 'sr' => 'Базел', 'sr-Latn' => 'Bazel']],

            // =============================================
            // USA (US) - Major diaspora cities
            // =============================================
            ['geonames_id' => '4887398', 'country_code' => 'US', 'coordinates' => ['lat' => 41.850, 'lng' => -87.650],
                'translations' => ['en' => 'Chicago', 'sr' => 'Чикаго', 'sr-Latn' => 'Čikago']],
            ['geonames_id' => '5128581', 'country_code' => 'US', 'coordinates' => ['lat' => 40.714, 'lng' => -74.006],
                'translations' => ['en' => 'New York', 'sr' => 'Њујорк', 'sr-Latn' => 'Njujork']],
            ['geonames_id' => '5368361', 'country_code' => 'US', 'coordinates' => ['lat' => 34.052, 'lng' => -118.244],
                'translations' => ['en' => 'Los Angeles', 'sr' => 'Лос Анђелес', 'sr-Latn' => 'Los Anđeles']],

            // =============================================
            // SWEDEN (SE)
            // =============================================
            ['geonames_id' => '2673730', 'country_code' => 'SE', 'coordinates' => ['lat' => 59.329, 'lng' => 18.069],
                'translations' => ['en' => 'Stockholm', 'de' => 'Stockholm', 'sr' => 'Стокхолм', 'sr-Latn' => 'Stokholm']],
            ['geonames_id' => '2711537', 'country_code' => 'SE', 'coordinates' => ['lat' => 57.709, 'lng' => 11.967],
                'translations' => ['en' => 'Gothenburg', 'de' => 'Göteborg', 'sr' => 'Гетеборг', 'sr-Latn' => 'Geteborg', 'sv' => 'Göteborg']],
            ['geonames_id' => '2692969', 'country_code' => 'SE', 'coordinates' => ['lat' => 55.605, 'lng' => 13.000],
                'translations' => ['en' => 'Malmö', 'de' => 'Malmö', 'sr' => 'Малме', 'sr-Latn' => 'Malme', 'sv' => 'Malmö']],

            // =============================================
            // AUSTRALIA (AU)
            // =============================================
            ['geonames_id' => '2147714', 'country_code' => 'AU', 'coordinates' => ['lat' => -33.868, 'lng' => 151.209],
                'translations' => ['en' => 'Sydney', 'sr' => 'Сиднеј', 'sr-Latn' => 'Sidnej']],
            ['geonames_id' => '2158177', 'country_code' => 'AU', 'coordinates' => ['lat' => -37.814, 'lng' => 144.963],
                'translations' => ['en' => 'Melbourne', 'sr' => 'Мелбурн', 'sr-Latn' => 'Melburn']],

            // =============================================
            // CANADA (CA)
            // =============================================
            ['geonames_id' => '6167865', 'country_code' => 'CA', 'coordinates' => ['lat' => 43.651, 'lng' => -79.347],
                'translations' => ['en' => 'Toronto', 'sr' => 'Торонто', 'sr-Latn' => 'Toronto']],
        ];
    }
}