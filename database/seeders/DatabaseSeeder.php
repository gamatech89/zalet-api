<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->command->info('Starting database seeding...');

        $this->call([
            UserSeeder::class,
            GiftSeeder::class,
            MediaSeeder::class,
            GeoNamesSeeder::class,
            AchievementSeeder::class,
        ]);

        $this->command->info('Database seeding completed!');
    }
}
