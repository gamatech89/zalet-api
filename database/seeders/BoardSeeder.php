<?php

namespace Database\Seeders;

use App\Models\Board;
use App\Models\BoardPost;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class BoardSeeder extends Seeder
{
    public function run(): void
    {
        // Create boards for key diaspora cities
        $boards = [
            ['name' => 'Wien', 'slug' => 'wien', 'country_code' => 'AT', 'city' => 'Vienna', 'description' => 'Community board for Vienna, Austria'],
            ['name' => 'Berlin', 'slug' => 'berlin', 'country_code' => 'DE', 'city' => 'Berlin', 'description' => 'Community board for Berlin, Germany'],
            ['name' => 'München', 'slug' => 'muenchen', 'country_code' => 'DE', 'city' => 'Munich', 'description' => 'Community board for Munich, Germany'],
            ['name' => 'Zürich', 'slug' => 'zuerich', 'country_code' => 'CH', 'city' => 'Zurich', 'description' => 'Community board for Zurich, Switzerland'],
            ['name' => 'Chicago', 'slug' => 'chicago', 'country_code' => 'US', 'city' => 'Chicago', 'description' => 'Community board for Chicago, USA'],
            ['name' => 'New York', 'slug' => 'new-york', 'country_code' => 'US', 'city' => 'New York', 'description' => 'Community board for New York, USA'],
            ['name' => 'Stockholm', 'slug' => 'stockholm', 'country_code' => 'SE', 'city' => 'Stockholm', 'description' => 'Community board for Stockholm, Sweden'],
            ['name' => 'Beograd', 'slug' => 'beograd', 'country_code' => 'RS', 'city' => 'Belgrade', 'description' => 'Community board for Belgrade, Serbia'],
        ];

        foreach ($boards as $boardData) {
            Board::firstOrCreate(
            ['slug' => $boardData['slug']],
                $boardData
            );
        }

        // Create sample posts if users exist
        $users = User::limit(5)->get();
        if ($users->isEmpty()) {
            $this->command->info('No users found — skipping sample posts.');
            return;
        }

        $wienBoard = Board::where('slug', 'wien')->first();
        if (!$wienBoard)
            return;

        $samplePosts = [
            [
                'title' => 'Driving to Belgrade, 2 seats 🚗',
                'body' => 'Leaving this Friday at 17:00 from Hauptbahnhof. Comfortable ride, AC, room watching, Splitting gas costs fairly.',
                'category' => 'ride',
                'type' => 'offer',
                'location_label' => 'Wien, Hauptbahnhof',
            ],
            [
                'title' => 'Looking for a flatmate in 10th district',
                'body' => 'Sunny room available from October 1st. 480€ warm. Close to U1 Reumannplatz. Prefer someone clean and quiet. DM for pics!',
                'category' => 'apartment',
                'type' => 'offer',
                'location_label' => 'Wien, 10. Bezirk',
            ],
            [
                'title' => 'Best Balkan bakery in 16th district?',
                'body' => 'Need recommendations for Burek. The one on Ottakringer Straße closed down. Where do you go?',
                'category' => 'general',
                'type' => 'question',
                'location_label' => 'Wien, 16. Bezirk',
            ],
            [
                'title' => 'Web developer looking for work',
                'body' => 'Full-stack developer with 5 years experience. React, Node.js, Laravel. Looking for contract or full-time in Wien area. Portfolio available.',
                'category' => 'job',
                'type' => 'need',
                'location_label' => 'Wien',
            ],
            [
                'title' => 'Finding Home Away from Home: A Journey of Connection',
                'body' => "Moving to a new continent wasn't just about changing time zones; it was about redefining who I was in a space where no one knew my name.\n\nBut I've learned that \"home\" is something you carry in your pockets. It's the smell of freshly roasted coffee in a small apartment, the sounds of music playing from a stranger's car.\n\nTo anyone just starting their journey: be patient. The roots you're planting now will take time to take hold, but they will grow.",
                'category' => 'advice',
                'type' => 'offer',
                'location_label' => 'Wien',
            ],
        ];

        foreach ($samplePosts as $i => $postData) {
            $user = $users[$i % $users->count()];
            BoardPost::firstOrCreate(
            ['title' => $postData['title'], 'board_id' => $wienBoard->id],
                array_merge($postData, [
                'user_id' => $user->id,
                'board_id' => $wienBoard->id,
            ])
            );
        }

        $this->command->info('Boards seeded: ' . Board::count() . ' boards, ' . BoardPost::count() . ' posts');
    }
}