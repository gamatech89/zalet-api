<?php

namespace Database\Seeders;

use App\Models\CoinPackage;
use Illuminate\Database\Seeder;

class CoinPackageSeeder extends Seeder
{
    public function run(): void
    {
        $packages = [
            ['coins' => 500,  'bonus' => 0,   'price_rsd' => 500,  'label' => null,         'sort_order' => 1],
            ['coins' => 1000, 'bonus' => 50,  'price_rsd' => 950,  'label' => 'popular',    'sort_order' => 2],
            ['coins' => 2500, 'bonus' => 200, 'price_rsd' => 2300, 'label' => null,         'sort_order' => 3],
            ['coins' => 5000, 'bonus' => 500, 'price_rsd' => 4500, 'label' => 'best_value', 'sort_order' => 4],
        ];

        foreach ($packages as $pkg) {
            CoinPackage::firstOrCreate(
                ['coins' => $pkg['coins'], 'price_rsd' => $pkg['price_rsd']],
                array_merge($pkg, ['is_active' => true]),
            );
        }
    }
}
