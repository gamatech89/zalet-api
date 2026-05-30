<?php

namespace Database\Seeders;

use App\Models\Media;
use App\Models\User;
use Illuminate\Database\Seeder;

class ScenaSeeder extends Seeder
{
    /**
     * Seed long-form Scena content.
     */
    public function run(): void
    {
        $admin = User::where('role', 'admin')->first()
            ?? User::first();

        if (!$admin) {
            $this->command->warn('No users found. Skipping ScenaSeeder.');
            return;
        }

        $scenaItems = [
            [
                'title' => 'Documentary: The Balkan Tech Scene',
                'description' => 'Exploring the rise of startups in Belgrade, Zagreb, and Sofia. How the diaspora is fueling the next wave of innovation across Europe.',
                'thumbnail_url' => 'https://images.unsplash.com/photo-1451187580459-43490279c0fa?w=600&h=340&fit=crop',
            ],
            [
                'title' => 'Best Rakija Bars in Berlin',
                'description' => "A guide to finding authentic spirits in the German capital. From underground spots to well-known Serbian restaurants.",
                'thumbnail_url' => 'https://images.unsplash.com/photo-1551024709-8f23befc6f87?w=600&h=340&fit=crop',
            ],
            [
                'title' => 'Vienna Serbian Community Meetup 2026',
                'description' => 'Highlights from the biggest Serbian community gathering in Austria. Over 500 people attended this year.',
                'thumbnail_url' => 'https://images.unsplash.com/photo-1540575467063-178a50c2df87?w=600&h=340&fit=crop',
            ],
            [
                'title' => 'How I Built a Business from Vienna',
                'description' => 'From Čačak to Wien — a founder\'s journey building a tech company in the Austrian capital. Tips for diaspora entrepreneurs.',
                'thumbnail_url' => 'https://images.unsplash.com/photo-1497366216548-37526070297c?w=600&h=340&fit=crop',
            ],
            [
                'title' => 'Serbian Food Tour: Munich Edition',
                'description' => 'The best ćevapi, pljeskavice, and Serbian bakeries in Munich. A culinary journey through the diaspora.',
                'thumbnail_url' => 'https://images.unsplash.com/photo-1555939594-58d7cb561ad1?w=600&h=340&fit=crop',
            ],
            [
                'title' => 'Life in Zürich as a Serbian Expat',
                'description' => 'Cost of living, community life, finding your people abroad. A real talk about the diaspora experience in Switzerland.',
                'thumbnail_url' => 'https://images.unsplash.com/photo-1515488764276-beab7607c1e6?w=600&h=340&fit=crop',
            ],
            [
                'title' => 'Belgrade Nightlife Guide 2026',
                'description' => 'The ultimate guide to Savamala, Strahinjića Bana, and the floating clubs. Where to go when you visit home.',
                'thumbnail_url' => 'https://images.unsplash.com/photo-1516450360452-9312f5e86fc7?w=600&h=340&fit=crop',
            ],
            [
                'title' => 'Learning Serbian Abroad: Tips & Resources',
                'description' => 'How to keep your Serbian language skills sharp when you live abroad. Apps, podcasts, and community events.',
                'thumbnail_url' => 'https://images.unsplash.com/photo-1456513080510-7bf3a84b82f8?w=600&h=340&fit=crop',
            ],
            [
                'title' => 'Diaspora Sports Leagues: Football Edition',
                'description' => 'Serbian amateur football leagues across Europe. From Vienna to Stockholm, our community stays connected through sport.',
                'thumbnail_url' => 'https://images.unsplash.com/photo-1431324155629-1a6deb1dec8d?w=600&h=340&fit=crop',
            ],
        ];

        $count = 0;
        foreach ($scenaItems as $item) {
            $existing = Media::where('type', 'long_form')
                ->where('title', $item['title'])
                ->first();

            if (!$existing) {
                Media::create([
                    'user_id' => $admin->id,
                    'type' => 'long_form',
                    'provider' => 'native',
                    'url' => 'https://example.com/videos/placeholder.mp4',
                    'title' => $item['title'],
                    'description' => $item['description'],
                    'thumbnail_url' => $item['thumbnail_url'],
                    'size_bytes' => rand(50_000_000, 500_000_000),
                    'is_ppv' => false,
                    'price_coins' => 0,
                ]);
                $count++;
            }
        }

        $this->command->info("Scena seeded: {$count} new, " . Media::longForm()->count() . " total.");
    }
}