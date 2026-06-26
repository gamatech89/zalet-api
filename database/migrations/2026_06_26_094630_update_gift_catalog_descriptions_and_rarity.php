<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Update all gifts with proper descriptions, rarity levels, and star ratings.
     */
    public function up(): void
    {
        $gifts = [
            // Level 1 — Common (5 ZLC)
            [
                'name' => 'Ajvar',
                'level' => 1,
                'is_epic' => false,
                'is_rare' => false,
                'description' => 'Teglica domaćeg ajvara — poklon iz srca, direktno s babine bašte.',
            ],
            [
                'name' => 'Kafica',
                'level' => 1,
                'is_epic' => false,
                'is_rare' => false,
                'description' => 'Domaća kafa — jer bez kafe jutro ne postoji, a razgovor ne počinje.',
            ],
            [
                'name' => 'Upaljač',
                'level' => 1,
                'is_epic' => false,
                'is_rare' => false,
                'description' => 'Imaš upaljač? Svaki razgovor na terasi počinje ovako.',
            ],
            [
                'name' => 'Čarape',
                'level' => 1,
                'is_epic' => false,
                'is_rare' => false,
                'description' => 'Vunene čarape od babe — toplije od svakog zagrljaja.',
            ],

            // Level 2 — Uncommon (10–15 ZLC)
            [
                'name' => 'Rakija',
                'level' => 2,
                'is_epic' => false,
                'is_rare' => false,
                'description' => 'Čašica šljivovice za zdravlje! Ko ne pije, ne treba mu ni zdravlje.',
            ],
            [
                'name' => 'Pasoš',
                'level' => 2,
                'is_epic' => false,
                'is_rare' => false,
                'description' => 'Gastarbajterski pasoš — jedini dokument koji otvara sva vrata na Zapadu.',
            ],
            [
                'name' => 'Kafa sa šlagom',
                'level' => 2,
                'is_epic' => false,
                'is_rare' => false,
                'description' => 'Bečka kafa sa šlagom — za one koji znaju da zaslužuju malo više.',
            ],
            [
                'name' => 'Marlboro',
                'level' => 2,
                'is_epic' => false,
                'is_rare' => false,
                'description' => 'Kutija Marlbora — klasičan poklon od klasičnog čovjeka.',
            ],

            // Level 3 — Notable (25–50 ZLC)
            [
                'name' => 'Novčanik',
                'level' => 3,
                'is_epic' => false,
                'is_rare' => false,
                'description' => 'Debeo novčanik — jer je lova ta koja vozi, a mi smo samo putnici.',
            ],
            [
                'name' => 'Buket',
                'level' => 3,
                'is_epic' => false,
                'is_rare' => false,
                'description' => 'Svježi buket ruža — jer nekad riječima jednostavno nije dovoljno.',
            ],
            [
                'name' => 'Nokia 3310',
                'level' => 3,
                'is_epic' => false,
                'is_rare' => false,
                'description' => 'Nokia 3310 — preživjela je sve ratove, krize i čak i tvoju bivšu.',
            ],
            [
                'name' => 'Zippo',
                'level' => 3,
                'is_epic' => false,
                'is_rare' => false,
                'description' => 'Zippo upaljač — onaj kultni klik koji nikad ne zaboravljaš.',
            ],
            [
                'name' => 'Šljivovica',
                'level' => 3,
                'is_epic' => false,
                'is_rare' => false,
                'description' => 'Flaša prave šljivovice. Doktori je mrze, ali je svi piju na svakoj svadbi.',
            ],
            [
                'name' => 'Cigara',
                'level' => 3,
                'is_epic' => false,
                'is_rare' => false,
                'description' => 'Kubanska cigara — jer si zaslužio savršen kraj savršenog dana.',
            ],

            // Level 4 — Rare (100–200 ZLC)
            [
                'name' => 'Lav',
                'level' => 4,
                'is_epic' => false,
                'is_rare' => true,
                'description' => 'Balkanski lav — simbol snage i ponosa. Samo za prave gazde.',
            ],
            [
                'name' => 'Ray-Ban',
                'level' => 4,
                'is_epic' => false,
                'is_rare' => true,
                'description' => 'Originalni Ray-Ban — jer lažnjaci se kupuju, a original se poklanja.',
            ],
            [
                'name' => 'Golf Dvojka',
                'level' => 4,
                'is_epic' => false,
                'is_rare' => true,
                'description' => 'Balkanski Ferrari. 0-100 za 3 minute, parkiraj gdje god hoćeš.',
            ],
            [
                'name' => 'Rezervisan',
                'level' => 4,
                'is_epic' => false,
                'is_rare' => true,
                'description' => 'VIP rezervacija — jer pravi gazda ne čeka u redu nikad i nigdje.',
            ],

            // Level 5 — Legendary / Epic (500–1000 ZLC)
            [
                'name' => 'Rolex',
                'level' => 5,
                'is_epic' => true,
                'is_rare' => false,
                'description' => 'Zlatni Rolex — ne pitaj odakle je. Samo nosi ga i uživaj.',
            ],
            [
                'name' => 'Meze Plata',
                'level' => 5,
                'is_epic' => true,
                'is_rare' => false,
                'description' => 'Kraljevska meze plata za cijeli stol — za one koji znaju kako se živi.',
            ],
        ];

        foreach ($gifts as $data) {
            DB::table('gift_catalog')
                ->where('name', $data['name'])
                ->update([
                    'level'       => $data['level'],
                    'is_epic'     => $data['is_epic'],
                    'is_rare'     => $data['is_rare'],
                    'description' => $data['description'],
                ]);
        }
    }

    public function down(): void
    {
        DB::table('gift_catalog')->update([
            'level'       => 1,
            'is_epic'     => false,
            'is_rare'     => false,
            'description' => null,
        ]);
    }
};
