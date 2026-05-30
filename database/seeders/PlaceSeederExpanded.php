<?php

namespace Database\Seeders;

use App\Models\Place;
use App\Models\PlaceTranslation;
use Illuminate\Database\Seeder;

class PlaceSeederExpanded extends Seeder
{
    public function run(): void
    {
        $places = $this->getPlaces();
        $count = 0;

        foreach ($places as $placeData) {
            $existing = Place::where('external_id', $placeData['geonames_id'])->first();
            if ($existing) {
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

        $this->command->info("Expanded places seeded: {$count} new, " . Place::count() . " total.");
    }

    private function getPlaces(): array
    {
        return [
            // =============================================
            // SERBIA — ALL MUNICIPALITIES & TOWNS
            // =============================================
            // Vojvodina
            ['geonames_id' => 'rs-ada', 'country_code' => 'RS', 'translations' => ['en' => 'Ada', 'sr' => 'Ада', 'sr-Latn' => 'Ada']],
            ['geonames_id' => 'rs-alibunar', 'country_code' => 'RS', 'translations' => ['en' => 'Alibunar', 'sr' => 'Алибунар', 'sr-Latn' => 'Alibunar']],
            ['geonames_id' => 'rs-apatin', 'country_code' => 'RS', 'translations' => ['en' => 'Apatin', 'sr' => 'Апатин', 'sr-Latn' => 'Apatin']],
            ['geonames_id' => 'rs-backapalanka', 'country_code' => 'RS', 'translations' => ['en' => 'Bačka Palanka', 'sr' => 'Бачка Паланка', 'sr-Latn' => 'Bačka Palanka']],
            ['geonames_id' => 'rs-backatopola', 'country_code' => 'RS', 'translations' => ['en' => 'Bačka Topola', 'sr' => 'Бачка Топола', 'sr-Latn' => 'Bačka Topola']],
            ['geonames_id' => 'rs-backipetrovac', 'country_code' => 'RS', 'translations' => ['en' => 'Bački Petrovac', 'sr' => 'Бачки Петровац', 'sr-Latn' => 'Bački Petrovac']],
            ['geonames_id' => 'rs-becej', 'country_code' => 'RS', 'translations' => ['en' => 'Bečej', 'sr' => 'Бечеј', 'sr-Latn' => 'Bečej']],
            ['geonames_id' => 'rs-belacrkva', 'country_code' => 'RS', 'translations' => ['en' => 'Bela Crkva', 'sr' => 'Бела Црква', 'sr-Latn' => 'Bela Crkva']],
            ['geonames_id' => 'rs-beocin', 'country_code' => 'RS', 'translations' => ['en' => 'Beočin', 'sr' => 'Беочин', 'sr-Latn' => 'Beočin']],
            ['geonames_id' => 'rs-vrbas', 'country_code' => 'RS', 'translations' => ['en' => 'Vrbas', 'sr' => 'Врбас', 'sr-Latn' => 'Vrbas']],
            ['geonames_id' => 'rs-vrsac', 'country_code' => 'RS', 'translations' => ['en' => 'Vršac', 'sr' => 'Вршац', 'sr-Latn' => 'Vršac']],
            ['geonames_id' => 'rs-indjija', 'country_code' => 'RS', 'translations' => ['en' => 'Inđija', 'sr' => 'Инђија', 'sr-Latn' => 'Inđija']],
            ['geonames_id' => 'rs-irig', 'country_code' => 'RS', 'translations' => ['en' => 'Irig', 'sr' => 'Ириг', 'sr-Latn' => 'Irig']],
            ['geonames_id' => 'rs-kanjiza', 'country_code' => 'RS', 'translations' => ['en' => 'Kanjiža', 'sr' => 'Кањижа', 'sr-Latn' => 'Kanjiža']],
            ['geonames_id' => 'rs-kovacica', 'country_code' => 'RS', 'translations' => ['en' => 'Kovačica', 'sr' => 'Ковачица', 'sr-Latn' => 'Kovačica']],
            ['geonames_id' => 'rs-kovin', 'country_code' => 'RS', 'translations' => ['en' => 'Kovin', 'sr' => 'Ковин', 'sr-Latn' => 'Kovin']],
            ['geonames_id' => 'rs-kula', 'country_code' => 'RS', 'translations' => ['en' => 'Kula', 'sr' => 'Кула', 'sr-Latn' => 'Kula']],
            ['geonames_id' => 'rs-mali-idjos', 'country_code' => 'RS', 'translations' => ['en' => 'Mali Iđoš', 'sr' => 'Мали Иђош', 'sr-Latn' => 'Mali Iđoš']],
            ['geonames_id' => 'rs-novi-knezevac', 'country_code' => 'RS', 'translations' => ['en' => 'Novi Kneževac', 'sr' => 'Нови Кнежевац', 'sr-Latn' => 'Novi Kneževac']],
            ['geonames_id' => 'rs-odzaci', 'country_code' => 'RS', 'translations' => ['en' => 'Odžaci', 'sr' => 'Оџаци', 'sr-Latn' => 'Odžaci']],
            ['geonames_id' => 'rs-opovo', 'country_code' => 'RS', 'translations' => ['en' => 'Opovo', 'sr' => 'Опово', 'sr-Latn' => 'Opovo']],
            ['geonames_id' => 'rs-pecinci', 'country_code' => 'RS', 'translations' => ['en' => 'Pećinci', 'sr' => 'Пећинци', 'sr-Latn' => 'Pećinci']],
            ['geonames_id' => 'rs-plandiste', 'country_code' => 'RS', 'translations' => ['en' => 'Plandište', 'sr' => 'Пландиште', 'sr-Latn' => 'Plandište']],
            ['geonames_id' => 'rs-ruma', 'country_code' => 'RS', 'translations' => ['en' => 'Ruma', 'sr' => 'Рума', 'sr-Latn' => 'Ruma']],
            ['geonames_id' => 'rs-senta', 'country_code' => 'RS', 'translations' => ['en' => 'Senta', 'sr' => 'Сента', 'sr-Latn' => 'Senta']],
            ['geonames_id' => 'rs-sremskamitrovica', 'country_code' => 'RS', 'translations' => ['en' => 'Sremska Mitrovica', 'sr' => 'Сремска Митровица', 'sr-Latn' => 'Sremska Mitrovica']],
            ['geonames_id' => 'rs-sremskikarlovci', 'country_code' => 'RS', 'translations' => ['en' => 'Sremski Karlovci', 'sr' => 'Сремски Карловци', 'sr-Latn' => 'Sremski Karlovci']],
            ['geonames_id' => 'rs-starapazova', 'country_code' => 'RS', 'translations' => ['en' => 'Stara Pazova', 'sr' => 'Стара Пазова', 'sr-Latn' => 'Stara Pazova']],
            ['geonames_id' => 'rs-sid', 'country_code' => 'RS', 'translations' => ['en' => 'Šid', 'sr' => 'Шид', 'sr-Latn' => 'Šid']],
            ['geonames_id' => 'rs-temerin', 'country_code' => 'RS', 'translations' => ['en' => 'Temerin', 'sr' => 'Темерин', 'sr-Latn' => 'Temerin']],
            ['geonames_id' => 'rs-titel', 'country_code' => 'RS', 'translations' => ['en' => 'Titel', 'sr' => 'Тител', 'sr-Latn' => 'Titel']],
            ['geonames_id' => 'rs-coka', 'country_code' => 'RS', 'translations' => ['en' => 'Čoka', 'sr' => 'Чока', 'sr-Latn' => 'Čoka']],
            // Central Serbia
            ['geonames_id' => 'rs-aleksandrovac', 'country_code' => 'RS', 'translations' => ['en' => 'Aleksandrovac', 'sr' => 'Александровац', 'sr-Latn' => 'Aleksandrovac']],
            ['geonames_id' => 'rs-alexinac', 'country_code' => 'RS', 'translations' => ['en' => 'Aleksinac', 'sr' => 'Алексинац', 'sr-Latn' => 'Aleksinac']],
            ['geonames_id' => 'rs-arilje', 'country_code' => 'RS', 'translations' => ['en' => 'Arilje', 'sr' => 'Ариље', 'sr-Latn' => 'Arilje']],
            ['geonames_id' => 'rs-babusnica', 'country_code' => 'RS', 'translations' => ['en' => 'Babušnica', 'sr' => 'Бабушница', 'sr-Latn' => 'Babušnica']],
            ['geonames_id' => 'rs-batocina', 'country_code' => 'RS', 'translations' => ['en' => 'Batočina', 'sr' => 'Баточина', 'sr-Latn' => 'Batočina']],
            ['geonames_id' => 'rs-bajina-basta', 'country_code' => 'RS', 'translations' => ['en' => 'Bajina Bašta', 'sr' => 'Бајина Башта', 'sr-Latn' => 'Bajina Bašta']],
            ['geonames_id' => 'rs-bor', 'country_code' => 'RS', 'translations' => ['en' => 'Bor', 'sr' => 'Бор', 'sr-Latn' => 'Bor']],
            ['geonames_id' => 'rs-brus', 'country_code' => 'RS', 'translations' => ['en' => 'Brus', 'sr' => 'Брус', 'sr-Latn' => 'Brus']],
            ['geonames_id' => 'rs-bujanovac', 'country_code' => 'RS', 'translations' => ['en' => 'Bujanovac', 'sr' => 'Бујановац', 'sr-Latn' => 'Bujanovac']],
            ['geonames_id' => 'rs-cicevac', 'country_code' => 'RS', 'translations' => ['en' => 'Ćićevac', 'sr' => 'Ћићевац', 'sr-Latn' => 'Ćićevac']],
            ['geonames_id' => 'rs-cajetina', 'country_code' => 'RS', 'translations' => ['en' => 'Čajetina', 'sr' => 'Чајетина', 'sr-Latn' => 'Čajetina']],
            ['geonames_id' => 'rs-despotovac', 'country_code' => 'RS', 'translations' => ['en' => 'Despotovac', 'sr' => 'Деспотовац', 'sr-Latn' => 'Despotovac']],
            ['geonames_id' => 'rs-dimitrovgrad', 'country_code' => 'RS', 'translations' => ['en' => 'Dimitrovgrad', 'sr' => 'Димитровград', 'sr-Latn' => 'Dimitrovgrad']],
            ['geonames_id' => 'rs-doljevac', 'country_code' => 'RS', 'translations' => ['en' => 'Doljevac', 'sr' => 'Дољевац', 'sr-Latn' => 'Doljevac']],
            ['geonames_id' => 'rs-gadžinhan', 'country_code' => 'RS', 'translations' => ['en' => 'Gadžin Han', 'sr' => 'Гаџин Хан', 'sr-Latn' => 'Gadžin Han']],
            ['geonames_id' => 'rs-golubac', 'country_code' => 'RS', 'translations' => ['en' => 'Golubac', 'sr' => 'Голубац', 'sr-Latn' => 'Golubac']],
            ['geonames_id' => 'rs-gornjimilanovac2', 'country_code' => 'RS', 'translations' => ['en' => 'Guča', 'sr' => 'Гуча', 'sr-Latn' => 'Guča']],
            ['geonames_id' => 'rs-ivanjica', 'country_code' => 'RS', 'translations' => ['en' => 'Ivanjica', 'sr' => 'Ивањица', 'sr-Latn' => 'Ivanjica']],
            ['geonames_id' => 'rs-kladovo', 'country_code' => 'RS', 'translations' => ['en' => 'Kladovo', 'sr' => 'Кладово', 'sr-Latn' => 'Kladovo']],
            ['geonames_id' => 'rs-knic', 'country_code' => 'RS', 'translations' => ['en' => 'Knić', 'sr' => 'Кнић', 'sr-Latn' => 'Knić']],
            ['geonames_id' => 'rs-knjaževac', 'country_code' => 'RS', 'translations' => ['en' => 'Knjaževac', 'sr' => 'Књажевац', 'sr-Latn' => 'Knjaževac']],
            ['geonames_id' => 'rs-kosjerić', 'country_code' => 'RS', 'translations' => ['en' => 'Kosjerić', 'sr' => 'Косјерић', 'sr-Latn' => 'Kosjerić']],
            ['geonames_id' => 'rs-krupanj', 'country_code' => 'RS', 'translations' => ['en' => 'Krupanj', 'sr' => 'Крупањ', 'sr-Latn' => 'Krupanj']],
            ['geonames_id' => 'rs-kucevo', 'country_code' => 'RS', 'translations' => ['en' => 'Kučevo', 'sr' => 'Кучево', 'sr-Latn' => 'Kučevo']],
            ['geonames_id' => 'rs-kursumlija', 'country_code' => 'RS', 'translations' => ['en' => 'Kuršumlija', 'sr' => 'Куршумлија', 'sr-Latn' => 'Kuršumlija']],
            ['geonames_id' => 'rs-lajkovac', 'country_code' => 'RS', 'translations' => ['en' => 'Lajkovac', 'sr' => 'Лајковац', 'sr-Latn' => 'Lajkovac']],
            ['geonames_id' => 'rs-lapovo', 'country_code' => 'RS', 'translations' => ['en' => 'Lapovo', 'sr' => 'Лапово', 'sr-Latn' => 'Lapovo']],
            ['geonames_id' => 'rs-lebane', 'country_code' => 'RS', 'translations' => ['en' => 'Lebane', 'sr' => 'Лебане', 'sr-Latn' => 'Lebane']],
            ['geonames_id' => 'rs-lucani', 'country_code' => 'RS', 'translations' => ['en' => 'Lučani', 'sr' => 'Лучани', 'sr-Latn' => 'Lučani']],
            ['geonames_id' => 'rs-ljig2', 'country_code' => 'RS', 'translations' => ['en' => 'Ljubovija', 'sr' => 'Љубовија', 'sr-Latn' => 'Ljubovija']],
            ['geonames_id' => 'rs-majdanpek', 'country_code' => 'RS', 'translations' => ['en' => 'Majdanpek', 'sr' => 'Мајданпек', 'sr-Latn' => 'Majdanpek']],
            ['geonames_id' => 'rs-malozvornik', 'country_code' => 'RS', 'translations' => ['en' => 'Mali Zvornik', 'sr' => 'Мали Зворник', 'sr-Latn' => 'Mali Zvornik']],
            ['geonames_id' => 'rs-medvedja', 'country_code' => 'RS', 'translations' => ['en' => 'Medveđa', 'sr' => 'Медвеђа', 'sr-Latn' => 'Medveđa']],
            ['geonames_id' => 'rs-merošina', 'country_code' => 'RS', 'translations' => ['en' => 'Merošina', 'sr' => 'Мерошина', 'sr-Latn' => 'Merošina']],
            ['geonames_id' => 'rs-mionica', 'country_code' => 'RS', 'translations' => ['en' => 'Mionica', 'sr' => 'Мионица', 'sr-Latn' => 'Mionica']],
            ['geonames_id' => 'rs-negotin', 'country_code' => 'RS', 'translations' => ['en' => 'Negotin', 'sr' => 'Неготин', 'sr-Latn' => 'Negotin']],
            ['geonames_id' => 'rs-nova-varos', 'country_code' => 'RS', 'translations' => ['en' => 'Nova Varoš', 'sr' => 'Нова Варош', 'sr-Latn' => 'Nova Varoš']],
            ['geonames_id' => 'rs-osečina', 'country_code' => 'RS', 'translations' => ['en' => 'Osečina', 'sr' => 'Осечина', 'sr-Latn' => 'Osečina']],
            ['geonames_id' => 'rs-petrovacnamlavi', 'country_code' => 'RS', 'translations' => ['en' => 'Petrovac na Mlavi', 'sr' => 'Петровац на Млави', 'sr-Latn' => 'Petrovac na Mlavi']],
            ['geonames_id' => 'rs-presevo', 'country_code' => 'RS', 'translations' => ['en' => 'Preševo', 'sr' => 'Прешево', 'sr-Latn' => 'Preševo']],
            ['geonames_id' => 'rs-priboj', 'country_code' => 'RS', 'translations' => ['en' => 'Priboj', 'sr' => 'Прибој', 'sr-Latn' => 'Priboj']],
            ['geonames_id' => 'rs-prokuplje', 'country_code' => 'RS', 'translations' => ['en' => 'Prokuplje', 'sr' => 'Прокупље', 'sr-Latn' => 'Prokuplje']],
            ['geonames_id' => 'rs-raca', 'country_code' => 'RS', 'translations' => ['en' => 'Rača', 'sr' => 'Рача', 'sr-Latn' => 'Rača']],
            ['geonames_id' => 'rs-raska', 'country_code' => 'RS', 'translations' => ['en' => 'Raška', 'sr' => 'Рашка', 'sr-Latn' => 'Raška']],
            ['geonames_id' => 'rs-razanj', 'country_code' => 'RS', 'translations' => ['en' => 'Ražanj', 'sr' => 'Ражањ', 'sr-Latn' => 'Ražanj']],
            ['geonames_id' => 'rs-rekovac', 'country_code' => 'RS', 'translations' => ['en' => 'Rekovac', 'sr' => 'Рековац', 'sr-Latn' => 'Rekovac']],
            ['geonames_id' => 'rs-sjenica', 'country_code' => 'RS', 'translations' => ['en' => 'Sjenica', 'sr' => 'Сјеница', 'sr-Latn' => 'Sjenica']],
            ['geonames_id' => 'rs-sokobanja', 'country_code' => 'RS', 'translations' => ['en' => 'Sokobanja', 'sr' => 'Сокобања', 'sr-Latn' => 'Sokobanja']],
            ['geonames_id' => 'rs-surdulica', 'country_code' => 'RS', 'translations' => ['en' => 'Surdulica', 'sr' => 'Сурдулица', 'sr-Latn' => 'Surdulica']],
            ['geonames_id' => 'rs-topola', 'country_code' => 'RS', 'translations' => ['en' => 'Topola', 'sr' => 'Топола', 'sr-Latn' => 'Topola']],
            ['geonames_id' => 'rs-trgoviste', 'country_code' => 'RS', 'translations' => ['en' => 'Trgovište', 'sr' => 'Трговиште', 'sr-Latn' => 'Trgovište']],
            ['geonames_id' => 'rs-tutin', 'country_code' => 'RS', 'translations' => ['en' => 'Tutin', 'sr' => 'Тутин', 'sr-Latn' => 'Tutin']],
            ['geonames_id' => 'rs-ub', 'country_code' => 'RS', 'translations' => ['en' => 'Ub', 'sr' => 'Уб', 'sr-Latn' => 'Ub']],
            ['geonames_id' => 'rs-vladimirci', 'country_code' => 'RS', 'translations' => ['en' => 'Vladimirci', 'sr' => 'Владимирци', 'sr-Latn' => 'Vladimirci']],
            ['geonames_id' => 'rs-vladickihan', 'country_code' => 'RS', 'translations' => ['en' => 'Vladičin Han', 'sr' => 'Владичин Хан', 'sr-Latn' => 'Vladičin Han']],
            ['geonames_id' => 'rs-vlasotince', 'country_code' => 'RS', 'translations' => ['en' => 'Vlasotince', 'sr' => 'Власотинце', 'sr-Latn' => 'Vlasotince']],
            ['geonames_id' => 'rs-velika-plana', 'country_code' => 'RS', 'translations' => ['en' => 'Velika Plana', 'sr' => 'Велика Плана', 'sr-Latn' => 'Velika Plana']],
            ['geonames_id' => 'rs-veliko-gradiste', 'country_code' => 'RS', 'translations' => ['en' => 'Veliko Gradište', 'sr' => 'Велико Градиште', 'sr-Latn' => 'Veliko Gradište']],
            ['geonames_id' => 'rs-vrnjackabanja', 'country_code' => 'RS', 'translations' => ['en' => 'Vrnjačka Banja', 'sr' => 'Врњачка Бања', 'sr-Latn' => 'Vrnjačka Banja']],
            ['geonames_id' => 'rs-žagubica', 'country_code' => 'RS', 'translations' => ['en' => 'Žagubica', 'sr' => 'Жагубица', 'sr-Latn' => 'Žagubica']],
            ['geonames_id' => 'rs-žitoradja', 'country_code' => 'RS', 'translations' => ['en' => 'Žitorađa', 'sr' => 'Житорађа', 'sr-Latn' => 'Žitorađa']],
            // Belgrade neighborhoods/municipalities
            ['geonames_id' => 'rs-vozdovac', 'country_code' => 'RS', 'translations' => ['en' => 'Voždovac', 'sr' => 'Вождовац', 'sr-Latn' => 'Voždovac']],
            ['geonames_id' => 'rs-vracar', 'country_code' => 'RS', 'translations' => ['en' => 'Vračar', 'sr' => 'Врачар', 'sr-Latn' => 'Vračar']],
            ['geonames_id' => 'rs-cukarica', 'country_code' => 'RS', 'translations' => ['en' => 'Čukarica', 'sr' => 'Чукарица', 'sr-Latn' => 'Čukarica']],
            ['geonames_id' => 'rs-rakovica', 'country_code' => 'RS', 'translations' => ['en' => 'Rakovica', 'sr' => 'Раковица', 'sr-Latn' => 'Rakovica']],
            ['geonames_id' => 'rs-zvezdara', 'country_code' => 'RS', 'translations' => ['en' => 'Zvezdara', 'sr' => 'Звездара', 'sr-Latn' => 'Zvezdara']],
            ['geonames_id' => 'rs-palilula', 'country_code' => 'RS', 'translations' => ['en' => 'Palilula', 'sr' => 'Палилула', 'sr-Latn' => 'Palilula']],
            ['geonames_id' => 'rs-starigrad', 'country_code' => 'RS', 'translations' => ['en' => 'Stari Grad', 'sr' => 'Стари Град', 'sr-Latn' => 'Stari Grad']],
            ['geonames_id' => 'rs-savskivenac', 'country_code' => 'RS', 'translations' => ['en' => 'Savski Venac', 'sr' => 'Савски Венац', 'sr-Latn' => 'Savski Venac']],
            ['geonames_id' => 'rs-barajevo', 'country_code' => 'RS', 'translations' => ['en' => 'Barajevo', 'sr' => 'Барајево', 'sr-Latn' => 'Barajevo']],
            ['geonames_id' => 'rs-grocka', 'country_code' => 'RS', 'translations' => ['en' => 'Grocka', 'sr' => 'Гроцка', 'sr-Latn' => 'Grocka']],
            ['geonames_id' => 'rs-lazarevac', 'country_code' => 'RS', 'translations' => ['en' => 'Lazarevac', 'sr' => 'Лазаревац', 'sr-Latn' => 'Lazarevac']],
            ['geonames_id' => 'rs-mladenovac', 'country_code' => 'RS', 'translations' => ['en' => 'Mladenovac', 'sr' => 'Младеновац', 'sr-Latn' => 'Mladenovac']],
            ['geonames_id' => 'rs-sopot', 'country_code' => 'RS', 'translations' => ['en' => 'Sopot', 'sr' => 'Сопот', 'sr-Latn' => 'Sopot']],
            ['geonames_id' => 'rs-surcin', 'country_code' => 'RS', 'translations' => ['en' => 'Surčin', 'sr' => 'Сурчин', 'sr-Latn' => 'Surčin']],
            ['geonames_id' => 'rs-borča', 'country_code' => 'RS', 'translations' => ['en' => 'Borča', 'sr' => 'Борча', 'sr-Latn' => 'Borča']],
            // More key RS towns
            ['geonames_id' => 'rs-smederevska-palanka', 'country_code' => 'RS', 'translations' => ['en' => 'Smederevska Palanka', 'sr' => 'Смедеревска Паланка', 'sr-Latn' => 'Smederevska Palanka']],
            ['geonames_id' => 'rs-pozega', 'country_code' => 'RS', 'translations' => ['en' => 'Požega', 'sr' => 'Пожега', 'sr-Latn' => 'Požega']],
            ['geonames_id' => 'rs-cacak-zlatibor', 'country_code' => 'RS', 'translations' => ['en' => 'Zlatibor', 'sr' => 'Златибор', 'sr-Latn' => 'Zlatibor']],
            ['geonames_id' => 'rs-kopaonik', 'country_code' => 'RS', 'translations' => ['en' => 'Kopaonik', 'sr' => 'Копаоник', 'sr-Latn' => 'Kopaonik']],
            ['geonames_id' => 'rs-niskabanja', 'country_code' => 'RS', 'translations' => ['en' => 'Niška Banja', 'sr' => 'Нишка Бања', 'sr-Latn' => 'Niška Banja']],
            ['geonames_id' => 'rs-tara', 'country_code' => 'RS', 'translations' => ['en' => 'Tara', 'sr' => 'Тара', 'sr-Latn' => 'Tara']],

            // =============================================
            // MORE INTERNATIONAL — DIASPORA CITIES
            // =============================================
            // France
            ['geonames_id' => 'fr-paris', 'country_code' => 'FR', 'translations' => ['en' => 'Paris', 'sr' => 'Париз', 'sr-Latn' => 'Pariz', 'fr' => 'Paris']],
            ['geonames_id' => 'fr-lyon', 'country_code' => 'FR', 'translations' => ['en' => 'Lyon', 'sr' => 'Лион', 'sr-Latn' => 'Lion', 'fr' => 'Lyon']],
            ['geonames_id' => 'fr-marseille', 'country_code' => 'FR', 'translations' => ['en' => 'Marseille', 'sr' => 'Марсељ', 'sr-Latn' => 'Marselj', 'fr' => 'Marseille']],
            // Italy
            ['geonames_id' => 'it-rome', 'country_code' => 'IT', 'translations' => ['en' => 'Rome', 'sr' => 'Рим', 'sr-Latn' => 'Rim', 'it' => 'Roma']],
            ['geonames_id' => 'it-milan', 'country_code' => 'IT', 'translations' => ['en' => 'Milan', 'sr' => 'Милано', 'sr-Latn' => 'Milano', 'it' => 'Milano']],
            ['geonames_id' => 'it-trieste', 'country_code' => 'IT', 'translations' => ['en' => 'Trieste', 'sr' => 'Трст', 'sr-Latn' => 'Trst', 'it' => 'Trieste']],
            // UK
            ['geonames_id' => 'uk-london', 'country_code' => 'GB', 'translations' => ['en' => 'London', 'sr' => 'Лондон', 'sr-Latn' => 'London']],
            ['geonames_id' => 'uk-manchester', 'country_code' => 'GB', 'translations' => ['en' => 'Manchester', 'sr' => 'Манчестер', 'sr-Latn' => 'Mančester']],
            // Netherlands
            ['geonames_id' => 'nl-amsterdam', 'country_code' => 'NL', 'translations' => ['en' => 'Amsterdam', 'sr' => 'Амстердам', 'sr-Latn' => 'Amsterdam']],
            ['geonames_id' => 'nl-hague', 'country_code' => 'NL', 'translations' => ['en' => 'The Hague', 'sr' => 'Хаг', 'sr-Latn' => 'Hag', 'nl' => 'Den Haag']],
            // Denmark / Norway
            ['geonames_id' => 'dk-copenhagen', 'country_code' => 'DK', 'translations' => ['en' => 'Copenhagen', 'sr' => 'Копенхаген', 'sr-Latn' => 'Kopenhagen', 'da' => 'København']],
            ['geonames_id' => 'no-oslo', 'country_code' => 'NO', 'translations' => ['en' => 'Oslo', 'sr' => 'Осло', 'sr-Latn' => 'Oslo']],
            // More Germany
            ['geonames_id' => 'de-mannheim', 'country_code' => 'DE', 'translations' => ['en' => 'Mannheim', 'de' => 'Mannheim', 'sr' => 'Манхајм', 'sr-Latn' => 'Manhajm']],
            ['geonames_id' => 'de-augsburg', 'country_code' => 'DE', 'translations' => ['en' => 'Augsburg', 'de' => 'Augsburg', 'sr' => 'Аугсбург', 'sr-Latn' => 'Augsburg']],
            ['geonames_id' => 'de-essen', 'country_code' => 'DE', 'translations' => ['en' => 'Essen', 'de' => 'Essen', 'sr' => 'Есен', 'sr-Latn' => 'Esen']],
            ['geonames_id' => 'de-hannover', 'country_code' => 'DE', 'translations' => ['en' => 'Hanover', 'de' => 'Hannover', 'sr' => 'Хановер', 'sr-Latn' => 'Hanover']],
            ['geonames_id' => 'de-bremen', 'country_code' => 'DE', 'translations' => ['en' => 'Bremen', 'de' => 'Bremen', 'sr' => 'Бремен', 'sr-Latn' => 'Bremen']],
            ['geonames_id' => 'de-duisburg', 'country_code' => 'DE', 'translations' => ['en' => 'Duisburg', 'de' => 'Duisburg', 'sr' => 'Дуизбург', 'sr-Latn' => 'Duizburg']],
            ['geonames_id' => 'de-bochum', 'country_code' => 'DE', 'translations' => ['en' => 'Bochum', 'de' => 'Bochum', 'sr' => 'Бохум', 'sr-Latn' => 'Bohum']],
            ['geonames_id' => 'de-wuppertal', 'country_code' => 'DE', 'translations' => ['en' => 'Wuppertal', 'de' => 'Wuppertal', 'sr' => 'Вуперталт', 'sr-Latn' => 'Vupertal']],
            ['geonames_id' => 'de-bielefeld', 'country_code' => 'DE', 'translations' => ['en' => 'Bielefeld', 'de' => 'Bielefeld', 'sr' => 'Билефелд', 'sr-Latn' => 'Bilefeld']],
            // More Austria
            ['geonames_id' => 'at-villach', 'country_code' => 'AT', 'translations' => ['en' => 'Villach', 'de' => 'Villach', 'sr' => 'Филах', 'sr-Latn' => 'Filah']],
            ['geonames_id' => 'at-steyr', 'country_code' => 'AT', 'translations' => ['en' => 'Steyr', 'de' => 'Steyr', 'sr' => 'Штајр', 'sr-Latn' => 'Štajr']],
            ['geonames_id' => 'at-dornbirn', 'country_code' => 'AT', 'translations' => ['en' => 'Dornbirn', 'de' => 'Dornbirn', 'sr' => 'Дорнбирн', 'sr-Latn' => 'Dornbirn']],
            ['geonames_id' => 'at-wiener-neustadt', 'country_code' => 'AT', 'translations' => ['en' => 'Wiener Neustadt', 'de' => 'Wiener Neustadt', 'sr' => 'Бечко Ново Место', 'sr-Latn' => 'Bečko Novo Mesto']],
            // More Switzerland
            ['geonames_id' => 'ch-lausanne', 'country_code' => 'CH', 'translations' => ['en' => 'Lausanne', 'sr' => 'Лозана', 'sr-Latn' => 'Lozana', 'fr' => 'Lausanne']],
            ['geonames_id' => 'ch-winterthur', 'country_code' => 'CH', 'translations' => ['en' => 'Winterthur', 'de' => 'Winterthur', 'sr' => 'Винтертур', 'sr-Latn' => 'Vintertur']],
            ['geonames_id' => 'ch-lucerne', 'country_code' => 'CH', 'translations' => ['en' => 'Lucerne', 'de' => 'Luzern', 'sr' => 'Луцерн', 'sr-Latn' => 'Lucern']],
            ['geonames_id' => 'ch-stgallen', 'country_code' => 'CH', 'translations' => ['en' => 'St. Gallen', 'de' => 'St. Gallen', 'sr' => 'Санкт Гален', 'sr-Latn' => 'Sankt Galen']],
            // More USA
            ['geonames_id' => 'us-phoenix', 'country_code' => 'US', 'translations' => ['en' => 'Phoenix', 'sr' => 'Финикс', 'sr-Latn' => 'Finiks']],
            ['geonames_id' => 'us-houston', 'country_code' => 'US', 'translations' => ['en' => 'Houston', 'sr' => 'Хјустон', 'sr-Latn' => 'Hjuston']],
            ['geonames_id' => 'us-sanfrancisco', 'country_code' => 'US', 'translations' => ['en' => 'San Francisco', 'sr' => 'Сан Франциско', 'sr-Latn' => 'San Francisko']],
            ['geonames_id' => 'us-detroit', 'country_code' => 'US', 'translations' => ['en' => 'Detroit', 'sr' => 'Детроит', 'sr-Latn' => 'Detroit']],
            ['geonames_id' => 'us-indianapolis', 'country_code' => 'US', 'translations' => ['en' => 'Indianapolis', 'sr' => 'Индијанаполис', 'sr-Latn' => 'Indijanapolis']],
            ['geonames_id' => 'us-pittsburgh', 'country_code' => 'US', 'translations' => ['en' => 'Pittsburgh', 'sr' => 'Питсбург', 'sr-Latn' => 'Pitsburg']],
            ['geonames_id' => 'us-milwaukee', 'country_code' => 'US', 'translations' => ['en' => 'Milwaukee', 'sr' => 'Милвоки', 'sr-Latn' => 'Milvoki']],
            // More Australia
            ['geonames_id' => 'au-perth', 'country_code' => 'AU', 'translations' => ['en' => 'Perth', 'sr' => 'Перт', 'sr-Latn' => 'Pert']],
            ['geonames_id' => 'au-brisbane', 'country_code' => 'AU', 'translations' => ['en' => 'Brisbane', 'sr' => 'Бризбејн', 'sr-Latn' => 'Brizbejn']],
            ['geonames_id' => 'au-adelaide', 'country_code' => 'AU', 'translations' => ['en' => 'Adelaide', 'sr' => 'Аделаида', 'sr-Latn' => 'Adelaida']],
            // More Canada
            ['geonames_id' => 'ca-vancouver', 'country_code' => 'CA', 'translations' => ['en' => 'Vancouver', 'sr' => 'Ванкувер', 'sr-Latn' => 'Vankuver']],
            ['geonames_id' => 'ca-montreal', 'country_code' => 'CA', 'translations' => ['en' => 'Montreal', 'sr' => 'Монтреал', 'sr-Latn' => 'Montreal', 'fr' => 'Montréal']],
            ['geonames_id' => 'ca-ottawa', 'country_code' => 'CA', 'translations' => ['en' => 'Ottawa', 'sr' => 'Отава', 'sr-Latn' => 'Otava']],
            ['geonames_id' => 'ca-hamilton', 'country_code' => 'CA', 'translations' => ['en' => 'Hamilton', 'sr' => 'Хамилтон', 'sr-Latn' => 'Hamilton']],
            // Spain
            ['geonames_id' => 'es-madrid', 'country_code' => 'ES', 'translations' => ['en' => 'Madrid', 'sr' => 'Мадрид', 'sr-Latn' => 'Madrid']],
            ['geonames_id' => 'es-barcelona', 'country_code' => 'ES', 'translations' => ['en' => 'Barcelona', 'sr' => 'Барселона', 'sr-Latn' => 'Barselona']],
            // Greece / Cyprus / Turkey
            ['geonames_id' => 'gr-athens', 'country_code' => 'GR', 'translations' => ['en' => 'Athens', 'sr' => 'Атина', 'sr-Latn' => 'Atina', 'el' => 'Αθήνα']],
            ['geonames_id' => 'gr-thessaloniki', 'country_code' => 'GR', 'translations' => ['en' => 'Thessaloniki', 'sr' => 'Солун', 'sr-Latn' => 'Solun', 'el' => 'Θεσσαλονίκη']],
            ['geonames_id' => 'tr-istanbul', 'country_code' => 'TR', 'translations' => ['en' => 'Istanbul', 'sr' => 'Истанбул', 'sr-Latn' => 'Istanbul']],
            // Hungary / Romania
            ['geonames_id' => 'hu-budapest', 'country_code' => 'HU', 'translations' => ['en' => 'Budapest', 'sr' => 'Будимпешта', 'sr-Latn' => 'Budimpešta']],
            ['geonames_id' => 'hu-szeged', 'country_code' => 'HU', 'translations' => ['en' => 'Szeged', 'sr' => 'Сегедин', 'sr-Latn' => 'Segedin']],
            ['geonames_id' => 'ro-bucharest', 'country_code' => 'RO', 'translations' => ['en' => 'Bucharest', 'sr' => 'Букурешт', 'sr-Latn' => 'Bukurešt', 'ro' => 'București']],
            ['geonames_id' => 'ro-timisoara', 'country_code' => 'RO', 'translations' => ['en' => 'Timișoara', 'sr' => 'Темишвар', 'sr-Latn' => 'Temišvar', 'ro' => 'Timișoara']],
            // Czech Republic / Poland
            ['geonames_id' => 'cz-prague', 'country_code' => 'CZ', 'translations' => ['en' => 'Prague', 'sr' => 'Праг', 'sr-Latn' => 'Prag', 'cs' => 'Praha']],
            ['geonames_id' => 'pl-warsaw', 'country_code' => 'PL', 'translations' => ['en' => 'Warsaw', 'sr' => 'Варшава', 'sr-Latn' => 'Varšava', 'pl' => 'Warszawa']],
            // UAE / Middle East
            ['geonames_id' => 'ae-dubai', 'country_code' => 'AE', 'translations' => ['en' => 'Dubai', 'sr' => 'Дубаи', 'sr-Latn' => 'Dubai']],
            ['geonames_id' => 'ae-abudhabi', 'country_code' => 'AE', 'translations' => ['en' => 'Abu Dhabi', 'sr' => 'Абу Даби', 'sr-Latn' => 'Abu Dabi']],
        ];
    }
}
