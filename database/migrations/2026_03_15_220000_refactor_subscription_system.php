<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Create subscription_plans table (admin-managed global plans)
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');                    // "Premium", "VIP"
            $table->string('slug')->unique();          // "premium", "vip"
            $table->text('description')->nullable();
            $table->unsignedTinyInteger('level');       // 1=Premium, 2=VIP — higher = more access
            $table->decimal('price_monthly', 10, 2);   // RSD price per month
            $table->decimal('price_yearly', 10, 2)->nullable(); // RSD price per year (discount)
            $table->json('features')->nullable();       // ["HD streaming", "Early access"]
            $table->boolean('is_active')->default(true);
            $table->unsignedTinyInteger('sort_order')->default(1);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });

        // 2. Create creator_requests table
        Schema::create('creator_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->text('message')->nullable();
            $table->string('portfolio_url')->nullable();
            $table->string('status', 20)->default('pending'); // pending, approved, rejected
            $table->text('admin_notes')->nullable();
            $table->foreignUuid('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('status');
        });

        // 3. Refactor subscriptions table
        // Drop old foreign keys, indexes, and columns, add new ones
        Schema::table('subscriptions', function (Blueprint $table) {
            // Drop indexes that reference old columns (needed for SQLite compat)
            $table->dropIndex(['creator_id', 'is_active']);
            $table->dropIndex(['subscriber_id', 'is_active']);

            // Drop old FK constraints first
            $table->dropConstrainedForeignId('subscription_tier_id');
            $table->dropForeign(['creator_id']);
            $table->dropForeign(['subscriber_id']);

            // Drop old columns
            $table->dropColumn(['creator_id', 'subscriber_id', 'price_coins', 'is_active']);
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            // Add new columns
            $table->foreignUuid('user_id')->after('id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('subscription_plan_id')->after('user_id')->constrained('subscription_plans')->cascadeOnDelete();
            $table->string('billing_cycle', 10)->default('monthly')->after('subscription_plan_id'); // monthly, yearly
            $table->decimal('price_paid', 10, 2)->after('billing_cycle'); // RSD amount actually paid
            $table->string('status', 20)->default('active')->after('ends_at'); // active, cancelled, expired, past_due
            $table->boolean('auto_renew')->default(true)->after('status');
            $table->string('raiffeisen_order_id')->nullable()->after('auto_renew');
            $table->timestamp('cancelled_at')->nullable()->after('raiffeisen_order_id');

            // New indexes
            $table->index(['user_id', 'status']);
            $table->index('raiffeisen_order_id');
        });

        // 4. Update media table — replace tier-based access with plan-level access
        Schema::table('media', function (Blueprint $table) {
            // Drop old tier FK
            $table->dropConstrainedForeignId('required_tier_id');
            $table->dropColumn('ppv_free_for_subscribers');

            // Add plan-level column
            $table->unsignedTinyInteger('required_plan_level')->nullable()->after('access_level');
        });

        // 5. Drop subscription_tiers table
        Schema::dropIfExists('subscription_tiers');
    }

    public function down(): void
    {
        // Recreate subscription_tiers
        Schema::create('subscription_tiers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('creator_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('price_coins', 12, 2);
            $table->unsignedTinyInteger('sort_order')->default(1);
            $table->boolean('is_active')->default(true);
            $table->json('perks')->nullable();
            $table->timestamps();

            $table->index(['creator_id', 'is_active']);
        });

        // Revert media columns
        Schema::table('media', function (Blueprint $table) {
            $table->dropColumn('required_plan_level');
            $table->foreignUuid('required_tier_id')
                ->nullable()
                ->after('access_level')
                ->constrained('subscription_tiers')
                ->nullOnDelete();
            $table->boolean('ppv_free_for_subscribers')->default(false)->after('required_tier_id');
        });

        // Revert subscriptions table
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropForeign(['subscription_plan_id']);
            $table->dropColumn([
                'user_id', 'subscription_plan_id', 'billing_cycle',
                'price_paid', 'status', 'auto_renew',
                'raiffeisen_order_id', 'cancelled_at',
            ]);
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->foreignUuid('subscriber_id')->after('id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('creator_id')->after('subscriber_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('subscription_tier_id')->nullable()->after('creator_id')->constrained('subscription_tiers')->nullOnDelete();
            $table->decimal('price_coins', 12, 2)->after('subscription_tier_id');
            $table->boolean('is_active')->default(true)->after('ends_at');

            $table->index(['subscriber_id', 'is_active']);
            $table->index(['creator_id', 'is_active']);
        });

        Schema::dropIfExists('creator_requests');
        Schema::dropIfExists('subscription_plans');
    }
};
