<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            // Which saved card to charge on renewal
            $table->foreignUuid('payment_method_id')
                ->nullable()
                ->after('raiffeisen_order_id')
                ->constrained('payment_methods')
                ->nullOnDelete();
            // When to attempt next charge (set on subscribe and after each renewal)
            $table->date('next_billing_date')->nullable()->after('payment_method_id');
            // Retry tracking
            $table->unsignedTinyInteger('renewal_attempts')->default(0)->after('next_billing_date');
            $table->text('last_renewal_error')->nullable()->after('renewal_attempts');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn(['last_renewal_error', 'renewal_attempts', 'next_billing_date', 'payment_method_id']);
        });
    }
};
