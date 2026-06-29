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
                'key'         => 'coin_to_rsd_rate',
                'value'       => '1.2',
                'type'        => 'float',
                'description' => '1 ZaletCoin = X RSD. Koristi se za obračun isplata kreatorima.',
            ],
            [
                'key'         => 'withdrawal_fee_percent',
                'value'       => '2',
                'type'        => 'float',
                'description' => 'Procenat koji platforma uzima pri isplati coinova (kreatori).',
            ],
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
            [
                'key'         => 'ppv_creator_percent',
                'value'       => '60',
                'type'        => 'integer',
                'description' => 'Procenat od PPV kupovine koji ide kreatoru (ostatak ide platformi)',
            ],
            [
                'key'         => 'ppv_content_percent',
                'value'       => '25',
                'type'        => 'integer',
                'description' => 'Maksimalni procenat ukupnog sadržaja kreatora koji sme biti PPV',
            ],
            [
                'key'         => 'ppv_monthly_limit',
                'value'       => '3',
                'type'        => 'integer',
                'description' => 'Maksimalan broj PPV videa koje kreator može objaviti u jednom mesecu',
            ],
        ];

        foreach ($settings as $s) {
            AppSetting::firstOrCreate(['key' => $s['key']], $s);
        }
    }
}
