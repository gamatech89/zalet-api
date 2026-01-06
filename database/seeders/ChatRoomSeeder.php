<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\Duel\Enums\ChatRoomType;
use App\Domains\Duel\Models\ChatRoom;
use App\Domains\Identity\Models\Location;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seed public kafana chat rooms for major cities.
 */
final class ChatRoomSeeder extends Seeder
{
    /**
     * Kafana configurations for major cities.
     *
     * @var array<string, array{name: string, description: string, country: string, max_participants: int}>
     */
    private array $kafanas = [
        // Serbia
        'Belgrade' => [
            'name' => 'Kafana Beograd',
            'description' => 'Glavna kafana za sve Beograđane i goste iz celog sveta.',
            'country' => 'Serbia',
            'max_participants' => 1000,
        ],
        'Novi Sad' => [
            'name' => 'Kafana Novi Sad',
            'description' => 'Vojvođanska kafana za dobar provod.',
            'country' => 'Serbia',
            'max_participants' => 500,
        ],
        'Niš' => [
            'name' => 'Kafana Niš',
            'description' => 'Južnjačka kafana za pravi đir.',
            'country' => 'Serbia',
            'max_participants' => 500,
        ],
        'Kragujevac' => [
            'name' => 'Kafana Kragujevac',
            'description' => 'Šumadijska kafana.',
            'country' => 'Serbia',
            'max_participants' => 300,
        ],
        // Croatia
        'Zagreb' => [
            'name' => 'Kafana Zagreb',
            'description' => 'Zagrebačka kafana za sve Hrvate.',
            'country' => 'Croatia',
            'max_participants' => 500,
        ],
        'Split' => [
            'name' => 'Kafana Split',
            'description' => 'Dalmatinska kafana uz more.',
            'country' => 'Croatia',
            'max_participants' => 400,
        ],
        // Bosnia
        'Sarajevo' => [
            'name' => 'Kafana Sarajevo',
            'description' => 'Bosanska kafana - Sevdah i ćef.',
            'country' => 'Bosnia and Herzegovina',
            'max_participants' => 500,
        ],
        'Banja Luka' => [
            'name' => 'Kafana Banja Luka',
            'description' => 'Krajinska kafana.',
            'country' => 'Bosnia and Herzegovina',
            'max_participants' => 400,
        ],
        // Montenegro
        'Podgorica' => [
            'name' => 'Kafana Podgorica',
            'description' => 'Crnogorska kafana.',
            'country' => 'Montenegro',
            'max_participants' => 400,
        ],
        // North Macedonia
        'Skopje' => [
            'name' => 'Kafana Skopje',
            'description' => 'Makedonska kafana.',
            'country' => 'North Macedonia',
            'max_participants' => 400,
        ],
        // Slovenia
        'Ljubljana' => [
            'name' => 'Kafana Ljubljana',
            'description' => 'Slovenska kafana.',
            'country' => 'Slovenia',
            'max_participants' => 400,
        ],
        // Diaspora - Germany
        'Frankfurt' => [
            'name' => 'Dijaspora Frankfurt',
            'description' => 'Balkanska dijaspora u Frankfurtu.',
            'country' => 'Germany',
            'max_participants' => 600,
        ],
        'Munich' => [
            'name' => 'Dijaspora München',
            'description' => 'Balkanska dijaspora u Minhenu.',
            'country' => 'Germany',
            'max_participants' => 500,
        ],
        'Berlin' => [
            'name' => 'Dijaspora Berlin',
            'description' => 'Balkanska dijaspora u Berlinu.',
            'country' => 'Germany',
            'max_participants' => 500,
        ],
        // Diaspora - Austria
        'Vienna' => [
            'name' => 'Dijaspora Wien',
            'description' => 'Balkanska dijaspora u Beču.',
            'country' => 'Austria',
            'max_participants' => 600,
        ],
        // Diaspora - Switzerland
        'Zürich' => [
            'name' => 'Dijaspora Zürich',
            'description' => 'Balkanska dijaspora u Cirihu.',
            'country' => 'Switzerland',
            'max_participants' => 500,
        ],
        // Diaspora - Sweden
        'Stockholm' => [
            'name' => 'Dijaspora Stockholm',
            'description' => 'Balkanska dijaspora u Stokholmu.',
            'country' => 'Sweden',
            'max_participants' => 400,
        ],
        // USA
        'Chicago' => [
            'name' => 'Dijaspora Chicago',
            'description' => 'Balkanska dijaspora u Čikagu.',
            'country' => 'United States',
            'max_participants' => 500,
        ],
        'New York' => [
            'name' => 'Dijaspora New York',
            'description' => 'Balkanska dijaspora u Njujorku.',
            'country' => 'United States',
            'max_participants' => 500,
        ],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach ($this->kafanas as $city => $config) {
            // Find or create location
            $location = Location::firstOrCreate(
                ['city' => $city, 'country' => $config['country']],
                ['latitude' => 0, 'longitude' => 0]
            );

            // Create kafana if it doesn't exist
            ChatRoom::firstOrCreate(
                ['slug' => Str::slug($config['name'])],
                [
                    'uuid' => (string) Str::uuid(),
                    'name' => $config['name'],
                    'description' => $config['description'],
                    'type' => ChatRoomType::PUBLIC_KAFANA,
                    'location_id' => $location->id,
                    'max_participants' => $config['max_participants'],
                    'is_active' => true,
                    'meta' => ['is_official' => true],
                ]
            );
        }

        $this->command->info('Created ' . count($this->kafanas) . ' public kafana chat rooms.');
    }
}
