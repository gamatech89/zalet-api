<?php

namespace Database\Seeders;

use App\Models\AppSetting;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            [
                'key'         => 'transfer_fee_percent',
                'value'       => '10',
                'type'        => 'integer',
                'description' => 'Procenat koji platforma uzima pri direktnom slanju coina između korisnika (tip)',
            ],
            [
                'key'         => 'transfer_min_amount',
                'value'       => '10',
                'type'        => 'integer',
                'description' => 'Minimalni iznos u coinima koji se može poslati direktno drugom korisniku',
            ],
            [
                'key'         => 'gift_creator_percent',
                'value'       => '50',
                'type'        => 'integer',
                'description' => 'Procenat vrednosti gifta koji ide kreatoru (ostatak zadržava platforma)',
            ],
        ];

        foreach ($settings as $s) {
            AppSetting::firstOrCreate(['key' => $s['key']], $s);
        }
    }
}
