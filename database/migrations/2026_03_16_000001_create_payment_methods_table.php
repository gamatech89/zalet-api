<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Payment methods (saved cards via Raiffeisen tokenization)
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('card_brand', 20);       // visa, mastercard, dina, amex
            $table->string('last_four', 4);
            $table->string('expiry_month', 2);
            $table->string('expiry_year', 2);
            $table->text('gateway_token');            // Encrypted Raiffeisen token
            $table->boolean('is_default')->default(false);
            $table->string('label')->nullable();      // User-friendly name e.g. "My Visa"
            $table->timestamps();

            $table->index(['user_id', 'is_default']);
        });

        // Bank accounts (for withdrawals)
        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('bank_name');              // "Banca Intesa", "Raiffeisen"
            $table->text('account_number');            // Encrypted
            $table->string('last_four', 4);
            $table->boolean('is_default')->default(false);
            $table->string('label')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'is_default']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_accounts');
        Schema::dropIfExists('payment_methods');
    }
};
