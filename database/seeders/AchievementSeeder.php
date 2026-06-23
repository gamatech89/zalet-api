<?php

namespace Database\Seeders;

use App\Models\Achievement;
use Illuminate\Database\Seeder;

class AchievementSeeder extends Seeder
{
    public function run(): void
    {
        $achievements = [
            [
                'name' => 'Brbljivac',
                'description' => 'Šalji poruke drugim korisnicima',
                'icon' => 'chat',
                'event_type' => 'message_sent',
                'aggregation' => ['type' => 'count', 'criteria' => []],
                'tiers' => [
                    ['threshold' => 10, 'reward' => ['type' => 'coins', 'amount' => 50]],
                    ['threshold' => 50, 'reward' => ['type' => 'coins', 'amount' => 150]],
                    ['threshold' => 200, 'reward' => ['type' => 'coins', 'amount' => 500]],
                    ['threshold' => 500, 'reward' => ['type' => 'coins', 'amount' => 1000]],
                    ['threshold' => 1000, 'reward' => ['type' => 'coins', 'amount' => 2500]],
                ],
            ],
            [
                'name' => 'Darodavac',
                'description' => 'Šalji poklone drugim korisnicima',
                'icon' => 'gift',
                'event_type' => 'gift_sent',
                'aggregation' => ['type' => 'count', 'criteria' => []],
                'tiers' => [
                    ['threshold' => 5, 'reward' => ['type' => 'coins', 'amount' => 100]],
                    ['threshold' => 25, 'reward' => ['type' => 'coins', 'amount' => 300]],
                    ['threshold' => 100, 'reward' => ['type' => 'coins', 'amount' => 1000]],
                ],
            ],
            [
                'name' => 'Pratilac',
                'description' => 'Prati druge korisnike',
                'icon' => 'follow',
                'event_type' => 'user_followed',
                'aggregation' => ['type' => 'count', 'criteria' => []],
                'tiers' => [
                    ['threshold' => 5, 'reward' => ['type' => 'coins', 'amount' => 25]],
                    ['threshold' => 25, 'reward' => ['type' => 'coins', 'amount' => 100]],
                    ['threshold' => 100, 'reward' => ['type' => 'coins', 'amount' => 500]],
                ],
            ],
            [
                'name' => 'Zvezda',
                'description' => 'Skupljaj pratioce',
                'icon' => 'star',
                'event_type' => 'follower_gained',
                'aggregation' => ['type' => 'count', 'criteria' => []],
                'tiers' => [
                    ['threshold' => 10],
                    ['threshold' => 50, 'reward' => ['type' => 'coins', 'amount' => 200]],
                    ['threshold' => 250, 'reward' => ['type' => 'coins', 'amount' => 1000]],
                    ['threshold' => 1000, 'reward' => ['type' => 'coins', 'amount' => 5000]],
                ],
            ],
            [
                'name' => 'Strimovac',
                'description' => 'Pokreni live strimove',
                'icon' => 'stream',
                'event_type' => 'stream_started',
                'aggregation' => ['type' => 'count', 'criteria' => []],
                'tiers' => [
                    ['threshold' => 1, 'reward' => ['type' => 'coins', 'amount' => 50]],
                    ['threshold' => 10, 'reward' => ['type' => 'coins', 'amount' => 250]],
                    ['threshold' => 50, 'reward' => ['type' => 'coins', 'amount' => 1000]],
                ],
            ],
            [
                'name' => 'Navika',
                'description' => 'Budi aktivan svaki dan',
                'icon' => 'calendar',
                'event_type' => 'daily_login',
                'aggregation' => ['type' => 'sequence', 'criteria' => [], 'interval_unit' => 'days', 'interval_value' => 1],
                'tiers' => [
                    ['threshold' => 3, 'reward' => ['type' => 'coins', 'amount' => 25]],
                    ['threshold' => 7, 'reward' => ['type' => 'coins', 'amount' => 100]],
                    ['threshold' => 30, 'reward' => ['type' => 'coins', 'amount' => 500]],
                ],
            ],
            [
                'name' => 'Kreator',
                'description' => 'Objavljuj sadržaj',
                'icon' => 'create',
                'event_type' => 'media_posted',
                'aggregation' => ['type' => 'count', 'criteria' => []],
                'tiers' => [
                    ['threshold' => 1],
                    ['threshold' => 10, 'reward' => ['type' => 'coins', 'amount' => 100]],
                    ['threshold' => 50, 'reward' => ['type' => 'coins', 'amount' => 500]],
                    ['threshold' => 200, 'reward' => ['type' => 'coins', 'amount' => 2000]],
                ],
            ],
        ];

        foreach ($achievements as $data) {
            $achievement = Achievement::updateOrCreate(
                ['name' => $data['name']],
                [
                    'description' => $data['description'],
                    'icon' => $data['icon'],
                    'event_type' => $data['event_type'],
                    'aggregation' => $data['aggregation'],
                ],
            );

            foreach ($data['tiers'] as $index => $tierData) {
                $achievement->tiers()->updateOrCreate(
                    ['level' => $index + 1],
                    [
                        'threshold' => $tierData['threshold'],
                        'reward' => $tierData['reward'] ?? null,
                    ],
                );
            }
        }
    }
}
