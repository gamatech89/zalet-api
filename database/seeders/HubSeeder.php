<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Profile;
use App\Models\LiveStream;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class HubSeeder extends Seeder
{
    public function run(): void
    {
        // === AUSTRIA HUB ===
        
        // Wien (Vienna) - Major Cluster
        $this->createHubUser('kenan_d', 'Kenan D.', 'AT', 'Wien', 'BA', 'Mostar', true, true); // Creator + Live
        $this->createHubUser('lejla_s', 'Lejla S.', 'AT', 'Wien', 'BA', 'Sarajevo', true, false); // Creator
        $this->createHubUser('marko_i', 'Marko I.', 'AT', 'Wien', 'RS', 'Beograd', false, false);
        $this->createHubUser('ana_m', 'Ana M.', 'AT', 'Wien', 'HR', 'Zagreb', true, false);
        $this->createHubUser('damir_k', 'Damir K.', 'AT', 'Wien', 'BA', 'Tuzla', false, false);

        // Graz
        $this->createHubUser('amina_h', 'Amina H.', 'AT', 'Graz', 'BA', 'Sarajevo', true, true); // Creator + Live
        $this->createHubUser('stefan_j', 'Stefan J.', 'AT', 'Graz', 'RS', 'Novi Sad', false, false);
        $this->createHubUser('elma_b', 'Elma B.', 'AT', 'Graz', 'BA', 'Bihać', true, false);

        // Salzburg
        $this->createHubUser('denis_r', 'Denis R.', 'AT', 'Salzburg', 'HR', 'Split', false, false);
        $this->createHubUser('nina_v', 'Nina V.', 'AT', 'Salzburg', 'RS', 'Niš', true, false);

        // === GERMANY HUB ===

        // Berlin
        $this->createHubUser('filip_bg', 'Filip BG', 'DE', 'Berlin', 'RS', 'Beograd', true, true); // Creator + Live
        $this->createHubUser('maja_sa', 'Maja SA', 'DE', 'Berlin', 'BA', 'Sarajevo', true, false);
        $this->createHubUser('igor_zg', 'Igor ZG', 'DE', 'Berlin', 'HR', 'Zagreb', false, false);

        // Munich
        $this->createHubUser('sara_m', 'Sara M.', 'DE', 'München', 'BA', 'Mostar', true, false);
        $this->createHubUser('luka_p', 'Luka P.', 'DE', 'München', 'RS', 'Novi Sad', false, false);
    }

    private function createHubUser(
        string $username, 
        string $name, 
        string $currentCountry, 
        string $currentCity,
        string $hometownCountry,
        string $hometownCity,
        bool $isCreator = false,
        bool $isLive = false
    ) {
        // Check if user exists
        if (User::where('username', $username)->exists()) {
            return;
        }

        $user = User::create([
            'username' => $username,
            // 'name' => $name, // User model doesn't have name
            'email' => "{$username}@zalet.com",
            'password' => Hash::make('password'),
            'role' => $isCreator ? 'creator' : 'user',
            // 'is_creator' => $isCreator, // Not a column, uses role
        ]);

        $user->profile()->create([
            'bio' => "Living across borders. From {$hometownCity} to {$currentCity}.",
            'current_country' => $currentCountry,
            'current_city' => $currentCity,
            'hometown_country' => $hometownCountry,
            'hometown_city' => $hometownCity,
            'avatar_url' => "https://ui-avatars.com/api/?name=" . urlencode($name) . "&background=" . ($isCreator ? "D4AF37" : "1a1a1a") . "&color=" . ($isCreator ? "000" : "fff"),
        ]);

        if ($isLive) {
            $stream = LiveStream::create([
                'user_id' => $user->id,
                'title' => "Live from {$currentCity}! 🔴",
                // 'started_at' => now(), // Not a column
                'stream_key' => 'test_key_' . $user->id,
            ]);
            $stream->goLive();
        }
    }
}
