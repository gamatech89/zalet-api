<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Fix gift descriptions: replace ijekavica with ekavica.
     *
     * Changes:
     *   čovjeka  → čoveka
     *   Svježi   → Sveži
     *   riječima → rečima
     *   preživjela → preživela
     *   gdje     → gde
     *   nigdje   → nigde
     *   cijeli   → celi
     */
    public function up(): void
    {
        $fixes = [
            'Marlboro' => 'Kutija Marlbora — klasičan poklon od klasičnog čoveka.',
            'Buket'    => 'Sveži buket ruža — jer nekad rečima jednostavno nije dovoljno.',
            'Nokia 3310' => 'Nokia 3310 — preživela je sve ratove, krize i čak i tvoju bivšu.',
            'Golf Dvojka' => 'Balkanski Ferrari. 0-100 za 3 minute, parkiraj gde god hoćeš.',
            'Rezervisan'  => 'VIP rezervacija — jer pravi gazda ne čeka u redu nikad i nigde.',
            'Meze Plata'  => 'Kraljevska meze plata za celi sto — za one koji znaju kako se živi.',
        ];

        foreach ($fixes as $name => $description) {
            DB::table('gift_catalog')
                ->where('name', $name)
                ->update(['description' => $description]);
        }
    }

    public function down(): void
    {
        // Revert to ijekavica originals
        $originals = [
            'Marlboro'    => 'Kutija Marlbora — klasičan poklon od klasičnog čovjeka.',
            'Buket'       => 'Svježi buket ruža — jer nekad riječima jednostavno nije dovoljno.',
            'Nokia 3310'  => 'Nokia 3310 — preživjela je sve ratove, krize i čak i tvoju bivšu.',
            'Golf Dvojka' => 'Balkanski Ferrari. 0-100 za 3 minute, parkiraj gdje god hoćeš.',
            'Rezervisan'  => 'VIP rezervacija — jer pravi gazda ne čeka u redu nikad i nigdje.',
            'Meze Plata'  => 'Kraljevska meze plata za cijeli stol — za one koji znaju kako se živi.',
        ];

        foreach ($originals as $name => $description) {
            DB::table('gift_catalog')
                ->where('name', $name)
                ->update(['description' => $description]);
        }
    }
};
