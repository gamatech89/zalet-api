<?php

namespace Database\Seeders;

use App\Models\Gift;
use App\Models\GiftCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class GiftSeeder extends Seeder
{
    /**
     * Seed the gift catalog with 20 Balkan-themed gifts.
     */
    public function run(): void
    {
        // ── Create category ──
        $category = GiftCategory::updateOrCreate(
        ['slug' => 'balkan-special'],
        ['name' => 'Balkan Special', 'sort_order' => 1, 'is_active' => true]
        );

        // ── Ensure storage directories exist ──
        Storage::disk('public')->makeDirectory('gifts/2d');
        Storage::disk('public')->makeDirectory('gifts/3d');

        // ── Gift definitions mapped to icon files ──
        // Icon-N.png → 2D, D3-N.png → 3D
        $gifts = [
            ['num' => 1, 'name' => 'Ajvar', 'price' => 5, 'desc' => 'Domaći ajvar — zlato Balkana'],
            ['num' => 2, 'name' => 'Rakija', 'price' => 10, 'desc' => 'Čašica za zdravlje i veselje'],
            ['num' => 3, 'name' => 'Novčanik', 'price' => 25, 'desc' => 'Debeo novčanik, dobar znak'],
            ['num' => 4, 'name' => 'Pasoš', 'price' => 15, 'desc' => 'Gastarbajterski pasoš'],
            ['num' => 5, 'name' => 'Lav', 'price' => 100, 'desc' => 'Balkanski lav — simbol snage'],
            ['num' => 6, 'name' => 'Šljivovica', 'price' => 50, 'desc' => 'Flaša najbolje šljivovice'],
            ['num' => 7, 'name' => 'Kafica', 'price' => 5, 'desc' => 'Domaća kafa sa rahatlokumon'],
            ['num' => 8, 'name' => 'Buket', 'price' => 25, 'desc' => 'Buket ruža za posebnu osobu'],
            ['num' => 9, 'name' => 'Nokia 3310', 'price' => 50, 'desc' => 'Neuništivi Nokia — legenda'],
            ['num' => 10, 'name' => 'Golf Dvojka', 'price' => 200, 'desc' => 'Balkanski Ferrari'],
            ['num' => 11, 'name' => 'Rolex', 'price' => 500, 'desc' => 'Zlatni Rolex — samo za VIP'],
            ['num' => 12, 'name' => 'Upaljač', 'price' => 5, 'desc' => 'Imaš upaljač, brate?'],
            ['num' => 13, 'name' => 'Kafa sa šlagom', 'price' => 10, 'desc' => 'Bečka kafa za pravi užitak'],
            ['num' => 14, 'name' => 'Marlboro', 'price' => 10, 'desc' => 'Kutija Marlbora — klasika'],
            ['num' => 15, 'name' => 'Ray-Ban', 'price' => 100, 'desc' => 'Originalni Ray-Ban, naravno'],
            ['num' => 16, 'name' => 'Rezervisan', 'price' => 200, 'desc' => 'VIP sto — samo za tebe'],
            ['num' => 17, 'name' => 'Čarape', 'price' => 5, 'desc' => 'Vunene čarape od babe'],
            ['num' => 18, 'name' => 'Cigara', 'price' => 50, 'desc' => 'Kubanska cigara za feštu'],
            ['num' => 19, 'name' => 'Zippo', 'price' => 25, 'desc' => 'Zippo upaljač — stil i klasa'],
            ['num' => 20, 'name' => 'Meze Plata', 'price' => 1000, 'desc' => 'Meze plata — kraljevi obrok'],
        ];

        $designsPath = base_path('../designs');

        foreach ($gifts as $index => $gift) {
            $num = $gift['num'];

            // Copy 2D icon
            $icon2dSource = $num === 13
                ? "{$designsPath}/2D-gift-icons/Icon 13.png"
                : "{$designsPath}/2D-gift-icons/Icon-{$num}.png";
            $icon2dDest = "gifts/2d/icon-{$num}.png";

            if (File::exists($icon2dSource)) {
                Storage::disk('public')->put($icon2dDest, File::get($icon2dSource));
            }

            // Copy 3D icon
            $icon3dSource = "{$designsPath}/3D-gift-icons/D3-{$num}.png";
            $icon3dDest = "gifts/3d/icon-{$num}.png";

            if (File::exists($icon3dSource)) {
                Storage::disk('public')->put($icon3dDest, File::get($icon3dSource));
            }

            Gift::updateOrCreate(
            ['name' => $gift['name']],
            [
                'coin_price' => $gift['price'],
                'icon_url' => "/storage/{$icon2dDest}",
                'icon_2d' => $icon2dDest,
                'icon_3d' => $icon3dDest,
                'category_id' => $category->id,
                'sort_order' => $index + 1,
                'description' => $gift['desc'],
                'is_active' => true,
            ]
            );
        }

        $this->command->info("Gift catalog seeded with " . count($gifts) . " Balkan Special gifts.");
    }
}