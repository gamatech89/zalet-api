<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Wallet;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Seed sample users for development/testing.
     */
    public function run(): void
    {
        // Create admin user
        $admin = User::updateOrCreate(
            ['email' => 'admin@zalet.com'],
            [
                'username' => 'admin',
                'password' => 'password',
                'role' => 'admin',
                'is_legacy_founder' => true,
            ]
        );
        $admin->profile()->firstOrCreate([]);
        Wallet::firstOrCreate(['user_id' => $admin->id], ['balance' => 10000]);

        $this->command->info('Created admin user: admin@zalet.com');

        // Create creator users
        $creators = [
            ['email' => 'creator1@zalet.com', 'username' => 'creator_marko', 'is_legacy_founder' => true],
            ['email' => 'creator2@zalet.com', 'username' => 'creator_ana', 'is_legacy_founder' => false],
            ['email' => 'creator3@zalet.com', 'username' => 'creator_stefan', 'is_legacy_founder' => false],
            ['email' => 'creator4@zalet.com', 'username' => 'creator_jelena', 'is_legacy_founder' => true],
            ['email' => 'creator5@zalet.com', 'username' => 'creator_nikola', 'is_legacy_founder' => false],
        ];

        foreach ($creators as $creatorData) {
            $creator = User::updateOrCreate(
                ['email' => $creatorData['email']],
                [
                    'username' => $creatorData['username'],
                    'password' => 'password',
                    'role' => 'creator',
                    'is_legacy_founder' => $creatorData['is_legacy_founder'],
                ]
            );
            $creator->profile()->firstOrCreate([
                'bio' => "Hi! I'm {$creatorData['username']}. Follow me for great content!",
            ]);
            Wallet::firstOrCreate(['user_id' => $creator->id], ['balance' => 500]);
        }

        $this->command->info('Created 5 creator users');

        // Create regular users
        $users = [
            ['email' => 'user1@zalet.com', 'username' => 'fan_milan'],
            ['email' => 'user2@zalet.com', 'username' => 'fan_ivana'],
            ['email' => 'user3@zalet.com', 'username' => 'fan_dragan'],
            ['email' => 'user4@zalet.com', 'username' => 'fan_milica'],
            ['email' => 'user5@zalet.com', 'username' => 'fan_petar'],
            ['email' => 'user6@zalet.com', 'username' => 'fan_marija'],
            ['email' => 'user7@zalet.com', 'username' => 'fan_jovan'],
            ['email' => 'user8@zalet.com', 'username' => 'fan_sara'],
            ['email' => 'user9@zalet.com', 'username' => 'fan_luka'],
            ['email' => 'user10@zalet.com', 'username' => 'fan_tea'],
        ];

        foreach ($users as $userData) {
            $user = User::updateOrCreate(
                ['email' => $userData['email']],
                [
                    'username' => $userData['username'],
                    'password' => 'password',
                    'role' => 'user',
                    'is_legacy_founder' => false,
                ]
            );
            $user->profile()->firstOrCreate([]);
            Wallet::firstOrCreate(['user_id' => $user->id], ['balance' => 100]);
        }

        $this->command->info('Created 10 regular users');
    }
}
