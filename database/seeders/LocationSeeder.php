<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\Identity\Models\Location;
use Illuminate\Database\Seeder;

final class LocationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $locations = array_merge(
            $this->getSerbianCities(),
            $this->getBalkanCities(),
            $this->getDiasporaCities(),
        );

        foreach ($locations as $location) {
            Location::firstOrCreate(
                [
                    'city' => $location['city'],
                    'country_code' => $location['country_code'],
                ],
                [
                    'country' => $location['country'],
                    'latitude' => $location['latitude'] ?? null,
                    'longitude' => $location['longitude'] ?? null,
                ]
            );
        }
    }

    /**
     * @return array<array{city: string, country: string, country_code: string, latitude?: float, longitude?: float}>
     */
    private function getSerbianCities(): array
    {
        return [
            // Major cities
            ['city' => 'Beograd', 'country' => 'Srbija', 'country_code' => 'RS', 'latitude' => 44.8176, 'longitude' => 20.4633],
            ['city' => 'Novi Sad', 'country' => 'Srbija', 'country_code' => 'RS', 'latitude' => 45.2671, 'longitude' => 19.8335],
            ['city' => 'Niš', 'country' => 'Srbija', 'country_code' => 'RS', 'latitude' => 43.3209, 'longitude' => 21.8958],
            ['city' => 'Kragujevac', 'country' => 'Srbija', 'country_code' => 'RS', 'latitude' => 44.0128, 'longitude' => 20.9114],
            ['city' => 'Subotica', 'country' => 'Srbija', 'country_code' => 'RS', 'latitude' => 46.1003, 'longitude' => 19.6658],
            ['city' => 'Zrenjanin', 'country' => 'Srbija', 'country_code' => 'RS', 'latitude' => 45.3816, 'longitude' => 20.3903],
            ['city' => 'Pančevo', 'country' => 'Srbija', 'country_code' => 'RS', 'latitude' => 44.8708, 'longitude' => 20.6403],
            ['city' => 'Čačak', 'country' => 'Srbija', 'country_code' => 'RS', 'latitude' => 43.8914, 'longitude' => 20.3497],
            ['city' => 'Kruševac', 'country' => 'Srbija', 'country_code' => 'RS', 'latitude' => 43.5803, 'longitude' => 21.3269],
            ['city' => 'Kraljevo', 'country' => 'Srbija', 'country_code' => 'RS', 'latitude' => 43.7236, 'longitude' => 20.6869],
            ['city' => 'Novi Pazar', 'country' => 'Srbija', 'country_code' => 'RS', 'latitude' => 43.1367, 'longitude' => 20.5122],
            ['city' => 'Smederevo', 'country' => 'Srbija', 'country_code' => 'RS', 'latitude' => 44.6628, 'longitude' => 20.9275],
            ['city' => 'Leskovac', 'country' => 'Srbija', 'country_code' => 'RS', 'latitude' => 42.9981, 'longitude' => 21.9461],
            ['city' => 'Užice', 'country' => 'Srbija', 'country_code' => 'RS', 'latitude' => 43.8586, 'longitude' => 19.8425],
            ['city' => 'Valjevo', 'country' => 'Srbija', 'country_code' => 'RS', 'latitude' => 44.2758, 'longitude' => 19.8914],
            ['city' => 'Šabac', 'country' => 'Srbija', 'country_code' => 'RS', 'latitude' => 44.7489, 'longitude' => 19.6919],
            ['city' => 'Sombor', 'country' => 'Srbija', 'country_code' => 'RS', 'latitude' => 45.7742, 'longitude' => 19.1122],
            ['city' => 'Požarevac', 'country' => 'Srbija', 'country_code' => 'RS', 'latitude' => 44.6217, 'longitude' => 21.1869],
            ['city' => 'Pirot', 'country' => 'Srbija', 'country_code' => 'RS', 'latitude' => 43.1531, 'longitude' => 22.5856],
            ['city' => 'Vranje', 'country' => 'Srbija', 'country_code' => 'RS', 'latitude' => 42.5514, 'longitude' => 21.9000],
            ['city' => 'Bor', 'country' => 'Srbija', 'country_code' => 'RS', 'latitude' => 44.0747, 'longitude' => 22.0958],
            ['city' => 'Zaječar', 'country' => 'Srbija', 'country_code' => 'RS', 'latitude' => 43.9044, 'longitude' => 22.2589],
            ['city' => 'Kikinda', 'country' => 'Srbija', 'country_code' => 'RS', 'latitude' => 45.8300, 'longitude' => 20.4656],
            ['city' => 'Sremska Mitrovica', 'country' => 'Srbija', 'country_code' => 'RS', 'latitude' => 44.9764, 'longitude' => 19.6117],
            ['city' => 'Jagodina', 'country' => 'Srbija', 'country_code' => 'RS', 'latitude' => 43.9775, 'longitude' => 21.2614],
            ['city' => 'Vršac', 'country' => 'Srbija', 'country_code' => 'RS', 'latitude' => 45.1167, 'longitude' => 21.3000],
            ['city' => 'Prokuplje', 'country' => 'Srbija', 'country_code' => 'RS', 'latitude' => 43.2342, 'longitude' => 21.5881],
            ['city' => 'Loznica', 'country' => 'Srbija', 'country_code' => 'RS', 'latitude' => 44.5333, 'longitude' => 19.2261],
            ['city' => 'Negotin', 'country' => 'Srbija', 'country_code' => 'RS', 'latitude' => 44.2264, 'longitude' => 22.5319],
            ['city' => 'Aranđelovac', 'country' => 'Srbija', 'country_code' => 'RS', 'latitude' => 44.3083, 'longitude' => 20.5556],
            ['city' => 'Paraćin', 'country' => 'Srbija', 'country_code' => 'RS', 'latitude' => 43.8617, 'longitude' => 21.4092],
            ['city' => 'Gornji Milanovac', 'country' => 'Srbija', 'country_code' => 'RS', 'latitude' => 44.0264, 'longitude' => 20.4622],
            ['city' => 'Prijepolje', 'country' => 'Srbija', 'country_code' => 'RS', 'latitude' => 43.3869, 'longitude' => 19.6494],
            ['city' => 'Ruma', 'country' => 'Srbija', 'country_code' => 'RS', 'latitude' => 45.0083, 'longitude' => 19.8225],
            ['city' => 'Inđija', 'country' => 'Srbija', 'country_code' => 'RS', 'latitude' => 45.0483, 'longitude' => 20.0800],
            ['city' => 'Stara Pazova', 'country' => 'Srbija', 'country_code' => 'RS', 'latitude' => 44.9853, 'longitude' => 20.1608],
            ['city' => 'Bečej', 'country' => 'Srbija', 'country_code' => 'RS', 'latitude' => 45.6167, 'longitude' => 20.0500],
            ['city' => 'Novi Bečej', 'country' => 'Srbija', 'country_code' => 'RS', 'latitude' => 45.5972, 'longitude' => 20.1333],
            ['city' => 'Apatin', 'country' => 'Srbija', 'country_code' => 'RS', 'latitude' => 45.6717, 'longitude' => 18.9833],
            ['city' => 'Bačka Palanka', 'country' => 'Srbija', 'country_code' => 'RS', 'latitude' => 45.2500, 'longitude' => 19.4000],
            ['city' => 'Temerin', 'country' => 'Srbija', 'country_code' => 'RS', 'latitude' => 45.4083, 'longitude' => 19.8864],
            ['city' => 'Senta', 'country' => 'Srbija', 'country_code' => 'RS', 'latitude' => 45.9278, 'longitude' => 20.0778],
            ['city' => 'Kanjiža', 'country' => 'Srbija', 'country_code' => 'RS', 'latitude' => 46.0667, 'longitude' => 20.0500],
            ['city' => 'Ada', 'country' => 'Srbija', 'country_code' => 'RS', 'latitude' => 45.8028, 'longitude' => 20.1250],
            ['city' => 'Ćuprija', 'country' => 'Srbija', 'country_code' => 'RS', 'latitude' => 43.9297, 'longitude' => 21.3739],
        ];
    }

    /**
     * @return array<array{city: string, country: string, country_code: string, latitude?: float, longitude?: float}>
     */
    private function getBalkanCities(): array
    {
        return [
            // Bosnia and Herzegovina
            ['city' => 'Sarajevo', 'country' => 'Bosna i Hercegovina', 'country_code' => 'BA', 'latitude' => 43.8563, 'longitude' => 18.4131],
            ['city' => 'Banja Luka', 'country' => 'Bosna i Hercegovina', 'country_code' => 'BA', 'latitude' => 44.7722, 'longitude' => 17.1910],
            ['city' => 'Tuzla', 'country' => 'Bosna i Hercegovina', 'country_code' => 'BA', 'latitude' => 44.5384, 'longitude' => 18.6763],
            ['city' => 'Zenica', 'country' => 'Bosna i Hercegovina', 'country_code' => 'BA', 'latitude' => 44.2017, 'longitude' => 17.9078],
            ['city' => 'Mostar', 'country' => 'Bosna i Hercegovina', 'country_code' => 'BA', 'latitude' => 43.3438, 'longitude' => 17.8078],
            ['city' => 'Bijeljina', 'country' => 'Bosna i Hercegovina', 'country_code' => 'BA', 'latitude' => 44.7569, 'longitude' => 19.2144],
            ['city' => 'Brčko', 'country' => 'Bosna i Hercegovina', 'country_code' => 'BA', 'latitude' => 44.8728, 'longitude' => 18.8097],
            ['city' => 'Prijedor', 'country' => 'Bosna i Hercegovina', 'country_code' => 'BA', 'latitude' => 44.9797, 'longitude' => 16.7139],
            ['city' => 'Doboj', 'country' => 'Bosna i Hercegovina', 'country_code' => 'BA', 'latitude' => 44.7319, 'longitude' => 18.0869],
            ['city' => 'Trebinje', 'country' => 'Bosna i Hercegovina', 'country_code' => 'BA', 'latitude' => 42.7119, 'longitude' => 18.3436],

            // Montenegro
            ['city' => 'Podgorica', 'country' => 'Crna Gora', 'country_code' => 'ME', 'latitude' => 42.4304, 'longitude' => 19.2594],
            ['city' => 'Nikšić', 'country' => 'Crna Gora', 'country_code' => 'ME', 'latitude' => 42.7731, 'longitude' => 18.9444],
            ['city' => 'Herceg Novi', 'country' => 'Crna Gora', 'country_code' => 'ME', 'latitude' => 42.4531, 'longitude' => 18.5375],
            ['city' => 'Bar', 'country' => 'Crna Gora', 'country_code' => 'ME', 'latitude' => 42.0939, 'longitude' => 19.1000],
            ['city' => 'Budva', 'country' => 'Crna Gora', 'country_code' => 'ME', 'latitude' => 42.2864, 'longitude' => 18.8400],
            ['city' => 'Kotor', 'country' => 'Crna Gora', 'country_code' => 'ME', 'latitude' => 42.4247, 'longitude' => 18.7712],
            ['city' => 'Tivat', 'country' => 'Crna Gora', 'country_code' => 'ME', 'latitude' => 42.4367, 'longitude' => 18.6969],
            ['city' => 'Bijelo Polje', 'country' => 'Crna Gora', 'country_code' => 'ME', 'latitude' => 43.0386, 'longitude' => 19.7461],
            ['city' => 'Berane', 'country' => 'Crna Gora', 'country_code' => 'ME', 'latitude' => 42.8422, 'longitude' => 19.8703],
            ['city' => 'Cetinje', 'country' => 'Crna Gora', 'country_code' => 'ME', 'latitude' => 42.3931, 'longitude' => 18.9236],

            // North Macedonia
            ['city' => 'Skoplje', 'country' => 'Severna Makedonija', 'country_code' => 'MK', 'latitude' => 41.9981, 'longitude' => 21.4254],
            ['city' => 'Bitola', 'country' => 'Severna Makedonija', 'country_code' => 'MK', 'latitude' => 41.0297, 'longitude' => 21.3292],
            ['city' => 'Kumanovo', 'country' => 'Severna Makedonija', 'country_code' => 'MK', 'latitude' => 42.1322, 'longitude' => 21.7144],
            ['city' => 'Prilep', 'country' => 'Severna Makedonija', 'country_code' => 'MK', 'latitude' => 41.3442, 'longitude' => 21.5528],
            ['city' => 'Ohrid', 'country' => 'Severna Makedonija', 'country_code' => 'MK', 'latitude' => 41.1231, 'longitude' => 20.8016],
            ['city' => 'Tetovo', 'country' => 'Severna Makedonija', 'country_code' => 'MK', 'latitude' => 42.0103, 'longitude' => 20.9714],
            ['city' => 'Štip', 'country' => 'Severna Makedonija', 'country_code' => 'MK', 'latitude' => 41.7358, 'longitude' => 22.1914],
            ['city' => 'Veles', 'country' => 'Severna Makedonija', 'country_code' => 'MK', 'latitude' => 41.7153, 'longitude' => 21.7756],
            ['city' => 'Strumica', 'country' => 'Severna Makedonija', 'country_code' => 'MK', 'latitude' => 41.4378, 'longitude' => 22.6433],

            // Croatia
            ['city' => 'Zagreb', 'country' => 'Hrvatska', 'country_code' => 'HR', 'latitude' => 45.8150, 'longitude' => 15.9819],
            ['city' => 'Split', 'country' => 'Hrvatska', 'country_code' => 'HR', 'latitude' => 43.5081, 'longitude' => 16.4402],
            ['city' => 'Rijeka', 'country' => 'Hrvatska', 'country_code' => 'HR', 'latitude' => 45.3271, 'longitude' => 14.4422],
            ['city' => 'Osijek', 'country' => 'Hrvatska', 'country_code' => 'HR', 'latitude' => 45.5550, 'longitude' => 18.6955],
            ['city' => 'Dubrovnik', 'country' => 'Hrvatska', 'country_code' => 'HR', 'latitude' => 42.6507, 'longitude' => 18.0944],
            ['city' => 'Zadar', 'country' => 'Hrvatska', 'country_code' => 'HR', 'latitude' => 44.1194, 'longitude' => 15.2314],
            ['city' => 'Pula', 'country' => 'Hrvatska', 'country_code' => 'HR', 'latitude' => 44.8666, 'longitude' => 13.8496],
            ['city' => 'Šibenik', 'country' => 'Hrvatska', 'country_code' => 'HR', 'latitude' => 43.7350, 'longitude' => 15.8952],
            ['city' => 'Varaždin', 'country' => 'Hrvatska', 'country_code' => 'HR', 'latitude' => 46.3044, 'longitude' => 16.3378],
            ['city' => 'Vukovar', 'country' => 'Hrvatska', 'country_code' => 'HR', 'latitude' => 45.3517, 'longitude' => 19.0024],

            // Slovenia
            ['city' => 'Ljubljana', 'country' => 'Slovenija', 'country_code' => 'SI', 'latitude' => 46.0569, 'longitude' => 14.5058],
            ['city' => 'Maribor', 'country' => 'Slovenija', 'country_code' => 'SI', 'latitude' => 46.5547, 'longitude' => 15.6466],
            ['city' => 'Celje', 'country' => 'Slovenija', 'country_code' => 'SI', 'latitude' => 46.2361, 'longitude' => 15.2678],
            ['city' => 'Kranj', 'country' => 'Slovenija', 'country_code' => 'SI', 'latitude' => 46.2389, 'longitude' => 14.3556],
            ['city' => 'Koper', 'country' => 'Slovenija', 'country_code' => 'SI', 'latitude' => 45.5481, 'longitude' => 13.7300],
        ];
    }

    /**
     * @return array<array{city: string, country: string, country_code: string, latitude?: float, longitude?: float}>
     */
    private function getDiasporaCities(): array
    {
        return [
            // Germany
            ['city' => 'Berlin', 'country' => 'Nemačka', 'country_code' => 'DE', 'latitude' => 52.5200, 'longitude' => 13.4050],
            ['city' => 'München', 'country' => 'Nemačka', 'country_code' => 'DE', 'latitude' => 48.1351, 'longitude' => 11.5820],
            ['city' => 'Frankfurt', 'country' => 'Nemačka', 'country_code' => 'DE', 'latitude' => 50.1109, 'longitude' => 8.6821],
            ['city' => 'Stuttgart', 'country' => 'Nemačka', 'country_code' => 'DE', 'latitude' => 48.7758, 'longitude' => 9.1829],
            ['city' => 'Hamburg', 'country' => 'Nemačka', 'country_code' => 'DE', 'latitude' => 53.5511, 'longitude' => 9.9937],
            ['city' => 'Düsseldorf', 'country' => 'Nemačka', 'country_code' => 'DE', 'latitude' => 51.2277, 'longitude' => 6.7735],
            ['city' => 'Köln', 'country' => 'Nemačka', 'country_code' => 'DE', 'latitude' => 50.9375, 'longitude' => 6.9603],
            ['city' => 'Nürnberg', 'country' => 'Nemačka', 'country_code' => 'DE', 'latitude' => 49.4521, 'longitude' => 11.0767],
            ['city' => 'Dortmund', 'country' => 'Nemačka', 'country_code' => 'DE', 'latitude' => 51.5136, 'longitude' => 7.4653],
            ['city' => 'Hannover', 'country' => 'Nemačka', 'country_code' => 'DE', 'latitude' => 52.3759, 'longitude' => 9.7320],

            // Austria
            ['city' => 'Beč', 'country' => 'Austrija', 'country_code' => 'AT', 'latitude' => 48.2082, 'longitude' => 16.3738],
            ['city' => 'Grac', 'country' => 'Austrija', 'country_code' => 'AT', 'latitude' => 47.0707, 'longitude' => 15.4395],
            ['city' => 'Linc', 'country' => 'Austrija', 'country_code' => 'AT', 'latitude' => 48.3069, 'longitude' => 14.2858],
            ['city' => 'Salcburg', 'country' => 'Austrija', 'country_code' => 'AT', 'latitude' => 47.8095, 'longitude' => 13.0550],
            ['city' => 'Insbruk', 'country' => 'Austrija', 'country_code' => 'AT', 'latitude' => 47.2692, 'longitude' => 11.4041],

            // Switzerland
            ['city' => 'Cirih', 'country' => 'Švajcarska', 'country_code' => 'CH', 'latitude' => 47.3769, 'longitude' => 8.5417],
            ['city' => 'Ženeva', 'country' => 'Švajcarska', 'country_code' => 'CH', 'latitude' => 46.2044, 'longitude' => 6.1432],
            ['city' => 'Bazel', 'country' => 'Švajcarska', 'country_code' => 'CH', 'latitude' => 47.5596, 'longitude' => 7.5886],
            ['city' => 'Bern', 'country' => 'Švajcarska', 'country_code' => 'CH', 'latitude' => 46.9480, 'longitude' => 7.4474],
            ['city' => 'Lozana', 'country' => 'Švajcarska', 'country_code' => 'CH', 'latitude' => 46.5197, 'longitude' => 6.6323],

            // Sweden
            ['city' => 'Stokholm', 'country' => 'Švedska', 'country_code' => 'SE', 'latitude' => 59.3293, 'longitude' => 18.0686],
            ['city' => 'Geteborg', 'country' => 'Švedska', 'country_code' => 'SE', 'latitude' => 57.7089, 'longitude' => 11.9746],
            ['city' => 'Malme', 'country' => 'Švedska', 'country_code' => 'SE', 'latitude' => 55.6050, 'longitude' => 13.0038],

            // USA
            ['city' => 'Čikago', 'country' => 'SAD', 'country_code' => 'US', 'latitude' => 41.8781, 'longitude' => -87.6298],
            ['city' => 'Njujork', 'country' => 'SAD', 'country_code' => 'US', 'latitude' => 40.7128, 'longitude' => -74.0060],
            ['city' => 'Los Anđeles', 'country' => 'SAD', 'country_code' => 'US', 'latitude' => 34.0522, 'longitude' => -118.2437],
            ['city' => 'Detroit', 'country' => 'SAD', 'country_code' => 'US', 'latitude' => 42.3314, 'longitude' => -83.0458],
            ['city' => 'Klivlend', 'country' => 'SAD', 'country_code' => 'US', 'latitude' => 41.4993, 'longitude' => -81.6944],
            ['city' => 'Filadelfija', 'country' => 'SAD', 'country_code' => 'US', 'latitude' => 39.9526, 'longitude' => -75.1652],
            ['city' => 'San Francisco', 'country' => 'SAD', 'country_code' => 'US', 'latitude' => 37.7749, 'longitude' => -122.4194],
            ['city' => 'Feniks', 'country' => 'SAD', 'country_code' => 'US', 'latitude' => 33.4484, 'longitude' => -112.0740],

            // Canada
            ['city' => 'Toronto', 'country' => 'Kanada', 'country_code' => 'CA', 'latitude' => 43.6532, 'longitude' => -79.3832],
            ['city' => 'Vankuver', 'country' => 'Kanada', 'country_code' => 'CA', 'latitude' => 49.2827, 'longitude' => -123.1207],
            ['city' => 'Montreal', 'country' => 'Kanada', 'country_code' => 'CA', 'latitude' => 45.5017, 'longitude' => -73.5673],
            ['city' => 'Kalgar', 'country' => 'Kanada', 'country_code' => 'CA', 'latitude' => 51.0447, 'longitude' => -114.0719],

            // Australia
            ['city' => 'Sidnej', 'country' => 'Australija', 'country_code' => 'AU', 'latitude' => -33.8688, 'longitude' => 151.2093],
            ['city' => 'Melburn', 'country' => 'Australija', 'country_code' => 'AU', 'latitude' => -37.8136, 'longitude' => 144.9631],
            ['city' => 'Brizbejn', 'country' => 'Australija', 'country_code' => 'AU', 'latitude' => -27.4698, 'longitude' => 153.0251],
            ['city' => 'Pert', 'country' => 'Australija', 'country_code' => 'AU', 'latitude' => -31.9505, 'longitude' => 115.8605],

            // UK
            ['city' => 'London', 'country' => 'Velika Britanija', 'country_code' => 'GB', 'latitude' => 51.5074, 'longitude' => -0.1278],
            ['city' => 'Mančester', 'country' => 'Velika Britanija', 'country_code' => 'GB', 'latitude' => 53.4808, 'longitude' => -2.2426],
            ['city' => 'Birmingem', 'country' => 'Velika Britanija', 'country_code' => 'GB', 'latitude' => 52.4862, 'longitude' => -1.8904],

            // France
            ['city' => 'Pariz', 'country' => 'Francuska', 'country_code' => 'FR', 'latitude' => 48.8566, 'longitude' => 2.3522],
            ['city' => 'Lion', 'country' => 'Francuska', 'country_code' => 'FR', 'latitude' => 45.7640, 'longitude' => 4.8357],
            ['city' => 'Marsej', 'country' => 'Francuska', 'country_code' => 'FR', 'latitude' => 43.2965, 'longitude' => 5.3698],

            // Italy
            ['city' => 'Rim', 'country' => 'Italija', 'country_code' => 'IT', 'latitude' => 41.9028, 'longitude' => 12.4964],
            ['city' => 'Milano', 'country' => 'Italija', 'country_code' => 'IT', 'latitude' => 45.4642, 'longitude' => 9.1900],
            ['city' => 'Torino', 'country' => 'Italija', 'country_code' => 'IT', 'latitude' => 45.0703, 'longitude' => 7.6869],
            ['city' => 'Trst', 'country' => 'Italija', 'country_code' => 'IT', 'latitude' => 45.6495, 'longitude' => 13.7768],

            // Netherlands
            ['city' => 'Amsterdam', 'country' => 'Holandija', 'country_code' => 'NL', 'latitude' => 52.3676, 'longitude' => 4.9041],
            ['city' => 'Roterdam', 'country' => 'Holandija', 'country_code' => 'NL', 'latitude' => 51.9244, 'longitude' => 4.4777],
            ['city' => 'Hag', 'country' => 'Holandija', 'country_code' => 'NL', 'latitude' => 52.0705, 'longitude' => 4.3007],

            // Belgium
            ['city' => 'Brisel', 'country' => 'Belgija', 'country_code' => 'BE', 'latitude' => 50.8503, 'longitude' => 4.3517],
            ['city' => 'Antverpen', 'country' => 'Belgija', 'country_code' => 'BE', 'latitude' => 51.2194, 'longitude' => 4.4025],

            // Norway
            ['city' => 'Oslo', 'country' => 'Norveška', 'country_code' => 'NO', 'latitude' => 59.9139, 'longitude' => 10.7522],
            ['city' => 'Bergen', 'country' => 'Norveška', 'country_code' => 'NO', 'latitude' => 60.3913, 'longitude' => 5.3221],

            // Denmark
            ['city' => 'Kopenhagen', 'country' => 'Danska', 'country_code' => 'DK', 'latitude' => 55.6761, 'longitude' => 12.5683],

            // UAE
            ['city' => 'Dubai', 'country' => 'UAE', 'country_code' => 'AE', 'latitude' => 25.2048, 'longitude' => 55.2708],
            ['city' => 'Abu Dabi', 'country' => 'UAE', 'country_code' => 'AE', 'latitude' => 24.4539, 'longitude' => 54.3773],

            // Russia
            ['city' => 'Moskva', 'country' => 'Rusija', 'country_code' => 'RU', 'latitude' => 55.7558, 'longitude' => 37.6173],
            ['city' => 'Sankt Peterburg', 'country' => 'Rusija', 'country_code' => 'RU', 'latitude' => 59.9343, 'longitude' => 30.3351],

            // Hungary
            ['city' => 'Budimpešta', 'country' => 'Mađarska', 'country_code' => 'HU', 'latitude' => 47.4979, 'longitude' => 19.0402],

            // Czech Republic
            ['city' => 'Prag', 'country' => 'Češka', 'country_code' => 'CZ', 'latitude' => 50.0755, 'longitude' => 14.4378],

            // Poland
            ['city' => 'Varšava', 'country' => 'Poljska', 'country_code' => 'PL', 'latitude' => 52.2297, 'longitude' => 21.0122],
            ['city' => 'Krakov', 'country' => 'Poljska', 'country_code' => 'PL', 'latitude' => 50.0647, 'longitude' => 19.9450],

            // Romania
            ['city' => 'Bukurešt', 'country' => 'Rumunija', 'country_code' => 'RO', 'latitude' => 44.4268, 'longitude' => 26.1025],
            ['city' => 'Temišvar', 'country' => 'Rumunija', 'country_code' => 'RO', 'latitude' => 45.7489, 'longitude' => 21.2087],

            // Bulgaria
            ['city' => 'Sofija', 'country' => 'Bugarska', 'country_code' => 'BG', 'latitude' => 42.6977, 'longitude' => 23.3219],

            // Greece
            ['city' => 'Atina', 'country' => 'Grčka', 'country_code' => 'GR', 'latitude' => 37.9838, 'longitude' => 23.7275],
            ['city' => 'Solun', 'country' => 'Grčka', 'country_code' => 'GR', 'latitude' => 40.6401, 'longitude' => 22.9444],

            // Turkey
            ['city' => 'Istanbul', 'country' => 'Turska', 'country_code' => 'TR', 'latitude' => 41.0082, 'longitude' => 28.9784],
            ['city' => 'Ankara', 'country' => 'Turska', 'country_code' => 'TR', 'latitude' => 39.9334, 'longitude' => 32.8597],

            // Albania
            ['city' => 'Tirana', 'country' => 'Albanija', 'country_code' => 'AL', 'latitude' => 41.3275, 'longitude' => 19.8187],

            // Luxembourg
            ['city' => 'Luksemburg', 'country' => 'Luksemburg', 'country_code' => 'LU', 'latitude' => 49.6116, 'longitude' => 6.1319],

            // Cyprus
            ['city' => 'Nikozija', 'country' => 'Kipar', 'country_code' => 'CY', 'latitude' => 35.1856, 'longitude' => 33.3823],
        ];
    }
}
