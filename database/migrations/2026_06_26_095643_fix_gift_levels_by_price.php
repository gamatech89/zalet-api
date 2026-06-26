<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Recalculate gift levels for all gifts based on actual coin_price.
     *
     * Tiers:
     *   ★      1–30 ZLC   — Common
     *   ★★    31–200 ZLC  — Uncommon
     *   ★★★  201–600 ZLC  — Notable
     *   ★★★★ 601–1500 ZLC — Rare
     *   ★★★★★ 1501+ ZLC  — Legendary
     */
    public function up(): void
    {
        DB::statement("
            UPDATE gift_catalog
            SET level = CASE
                WHEN coin_price <=   30 THEN 1
                WHEN coin_price <=  200 THEN 2
                WHEN coin_price <=  600 THEN 3
                WHEN coin_price <= 1500 THEN 4
                ELSE 5
            END
        ");
    }

    public function down(): void
    {
        // Reset to 1 — original default
        DB::table('gift_catalog')->update(['level' => 1]);
    }
};
