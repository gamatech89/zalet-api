<?php

namespace Database\Seeders;

use App\Enums\AggregatorType;
use App\Enums\EventType;
use App\Models\Achievement;
use Illuminate\Database\Seeder;

class AchievementSeeder extends Seeder
{
    public function run(): void
    {
        $achievements = [
            [
                'name'             => 'First Deposit',
                'description'      => 'Made your first deposit.',
                'event_type'       => EventType::Deposit->value,
                'aggregator_type'  => AggregatorType::Count->value,
                'threshold'        => 1,
            ],
            [
                'name'             => 'Deposit x5',
                'description'      => 'Made 5 deposits.',
                'event_type'       => EventType::Deposit->value,
                'aggregator_type'  => AggregatorType::Count->value,
                'threshold'        => 5,
            ],
            [
                'name'             => 'High Roller',
                'description'      => 'Deposited 10,000 in total.',
                'event_type'       => EventType::Deposit->value,
                'aggregator_type'  => AggregatorType::Sum->value,
                'threshold'        => 10000,
            ],
            [
                'name'             => 'Big Shot',
                'description'      => 'Made a single deposit of 5,000 or more.',
                'event_type'       => EventType::Deposit->value,
                'aggregator_type'  => AggregatorType::Max->value,
                'threshold'        => 5000,
            ],
            [
                'name'             => 'Welcome Back',
                'description'      => 'Logged in 3 days in a row.',
                'event_type'       => EventType::Login->value,
                'aggregator_type'  => AggregatorType::Streak->value,
                'threshold'        => 3,
            ],
            [
                'name'             => 'Loyal',
                'description'      => 'Logged in 7 days in a row.',
                'event_type'       => EventType::Login->value,
                'aggregator_type'  => AggregatorType::Streak->value,
                'threshold'        => 7,
            ],
            [
                'name'             => 'Referral Rookie',
                'description'      => 'Referred your first friend.',
                'event_type'       => EventType::Referral->value,
                'aggregator_type'  => AggregatorType::Count->value,
                'threshold'        => 1,
            ],
            [
                'name'             => 'Connector',
                'description'      => 'Referred 5 friends.',
                'event_type'       => EventType::Referral->value,
                'aggregator_type'  => AggregatorType::Count->value,
                'threshold'        => 5,
            ],
            [
                'name'             => 'First Purchase',
                'description'      => 'Made your first purchase.',
                'event_type'       => EventType::Purchase->value,
                'aggregator_type'  => AggregatorType::Count->value,
                'threshold'        => 1,
            ],
            [
                'name'             => 'Profile Complete',
                'description'      => 'Finished setting up your profile.',
                'event_type'       => EventType::ProfileSetup->value,
                'aggregator_type'  => AggregatorType::Count->value,
                'threshold'        => 1,
            ],
        ];

        foreach ($achievements as $data) {
            Achievement::updateOrCreate(['name' => $data['name']], $data + ['is_active' => true]);
        }

        $this->command->info('Seeded ' . count($achievements) . ' achievements.');
    }
}