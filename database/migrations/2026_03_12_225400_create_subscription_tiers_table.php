<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Subscription Tiers
        Schema::create('subscription_tiers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('creator_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');           // "Bronze", "Silver", "Gold"
            $table->text('description')->nullable();
            $table->decimal('price_coins', 12, 2);
            $table->unsignedTinyInteger('sort_order')->default(1); // 1=lowest tier
            $table->boolean('is_active')->default(true);
            $table->json('perks')->nullable(); // ["Early access", "Exclusive chat"]
            $table->timestamps();

            $table->index(['creator_id', 'is_active']);
        });

        // 2. Link subscriptions to tiers
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->foreignUuid('subscription_tier_id')
                ->nullable()
                ->after('creator_id')
                ->constrained('subscription_tiers')
                ->nullOnDelete();
        });

        // 3. Content access control on media
        Schema::table('media', function (Blueprint $table) {
            $table->string('access_level', 20)->default('free')->after('price_coins');
            // 'free', 'subscribers', 'tier'
            $table->foreignUuid('required_tier_id')
                ->nullable()
                ->after('access_level')
                ->constrained('subscription_tiers')
                ->nullOnDelete();
            $table->boolean('ppv_free_for_subscribers')->default(false)->after('required_tier_id');
        });
    }

    public function down(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->dropConstrainedForeignId('required_tier_id');
            $table->dropColumn(['access_level', 'ppv_free_for_subscribers']);
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('subscription_tier_id');
        });

        Schema::dropIfExists('subscription_tiers');
    }
};
