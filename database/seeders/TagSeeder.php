<?php

namespace Database\Seeders;

use App\Models\Media;
use App\Models\Tag;
use Illuminate\Database\Seeder;

class TagSeeder extends Seeder
{
    public function run(): void
    {
        // Create tags
        $tags = [
            ['name' => 'music', 'label' => 'Muzika', 'color' => '#E53935'],
            ['name' => 'culture', 'label' => 'Kultura', 'color' => '#8E24AA'],
            ['name' => 'sports', 'label' => 'Sport', 'color' => '#43A047'],
            ['name' => 'food', 'label' => 'Hrana', 'color' => '#FB8C00'],
            ['name' => 'documentary', 'label' => 'Dokumentarci', 'color' => '#1E88E5'],
            ['name' => 'nightlife', 'label' => 'Noćni život', 'color' => '#D81B60'],
            ['name' => 'tech', 'label' => 'Tech', 'color' => '#00ACC1'],
            ['name' => 'travel', 'label' => 'Putovanja', 'color' => '#7CB342'],
            ['name' => 'lifestyle', 'label' => 'Lifestyle', 'color' => '#FF7043'],
        ];

        foreach ($tags as $tagData) {
            Tag::firstOrCreate(['name' => $tagData['name']], $tagData);
        }

        // Assign tags to Scena (long_form) media
        $tagMap = [
            'Documentary: The Balkan Tech Scene' => ['documentary', 'tech'],
            'Best Rakija Bars in Berlin' => ['food', 'nightlife', 'travel'],
            'Vienna Serbian Community Meetup 2026' => ['culture'],
            'How I Built a Business from Vienna' => ['documentary', 'tech'],
            'Serbian Food Tour: Munich Edition' => ['food', 'travel'],
            'Life in Zürich as a Serbian Expat' => ['lifestyle', 'documentary'],
            'Belgrade Nightlife Guide 2026' => ['nightlife', 'travel'],
            'Learning Serbian Abroad: Tips & Resources' => ['culture', 'lifestyle'],
            'Diaspora Sports Leagues: Football Edition' => ['sports', 'culture'],
        ];

        foreach ($tagMap as $title => $tagNames) {
            $media = Media::where('title', $title)->first();
            if ($media) {
                $tagIds = Tag::whereIn('name', $tagNames)->pluck('id')->toArray();
                $media->tags()->syncWithoutDetaching($tagIds);
            }
        }

        // Assign tags to Moments too
        $momentTagMap = [
            'Coffee culture' => ['food', 'lifestyle'],
            'Belgrade vibes' => ['travel', 'nightlife'],
            'Street art gems' => ['culture'],
            'Sunday league' => ['sports'],
            'Grandmas recipe' => ['food', 'culture'],
            'First snow' => ['lifestyle'],
        ];

        foreach ($momentTagMap as $title => $tagNames) {
            $media = Media::where('title', $title)->first();
            if ($media) {
                $tagIds = Tag::whereIn('name', $tagNames)->pluck('id')->toArray();
                $media->tags()->syncWithoutDetaching($tagIds);
            }
        }

        $this->command->info('Tags seeded: ' . Tag::count() . ' tags, ' .
            \DB::table('media_tag')->count() . ' tag assignments.');
    }
}