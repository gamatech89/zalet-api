<?php

namespace Database\Seeders;

use App\Models\Media;
use App\Models\User;
use Illuminate\Database\Seeder;

class MediaSeeder extends Seeder
{
    /**
     * Seed sample media content for development/testing.
     */
    public function run(): void
    {
        // Get creators
        $creators = User::where('role', 'creator')->get();

        if ($creators->isEmpty()) {
            $this->command->warn('No creators found. Run UserSeeder first.');
            return;
        }

        $momentTitles = [
            'Just finished a great workout! 💪',
            'Beautiful sunset from my balcony 🌅',
            'New recipe I tried today 🍝',
            'Behind the scenes of my latest project',
            'Throwback to summer vacation ☀️',
        ];

        $embedUrls = [
            'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'https://vimeo.com/148751763',
            'https://www.youtube.com/watch?v=jNQXAC9IVRw',
        ];

        foreach ($creators as $creator) {
            // Create moments for each creator
            foreach (array_slice($momentTitles, 0, rand(2, 4)) as $title) {
                Media::updateOrCreate(
                    [
                        'user_id' => $creator->id,
                        'title' => $title,
                    ],
                    [
                        'type' => 'moment',
                        'provider' => 'native',
                        'url' => '/storage/media/sample_video.mp4',
                        'is_ppv' => rand(0, 1) === 1,
                        'price_coins' => rand(0, 1) === 1 ? rand(10, 50) : null,
                        'description' => "Shared by {$creator->username}",
                    ]
                );
            }

            // Create cinema embeds for some creators
            if (rand(0, 1) === 1) {
                $embedUrl = $embedUrls[array_rand($embedUrls)];
                Media::updateOrCreate(
                    [
                        'user_id' => $creator->id,
                        'url' => $embedUrl,
                    ],
                    [
                        'type' => 'embed',
                        'provider' => 'youtube',
                        'title' => 'Check out this video I found!',
                        'is_ppv' => false,
                        'description' => "Curated by {$creator->username}",
                    ]
                );
            }
        }

        $totalMedia = Media::count();
        $this->command->info("Seeded {$totalMedia} media items across creators");
    }
}
