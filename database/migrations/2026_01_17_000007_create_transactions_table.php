<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('from_wallet_id')->nullable()->constrained('wallets')->nullOnDelete();
            $table->foreignUuid('to_wallet_id')->constrained('wallets')->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->enum('type', ['deposit', 'tip', 'subscription', 'withdrawal', 'ppv']);
            $table->enum('status', ['pending', 'completed', 'failed'])->default('pending');
            $table->string('raiffeisen_order_id')->nullable()->index();
            $table->foreignUuid('media_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('gift_id')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();

            $table->foreign('gift_id')->references('id')->on('gift_catalog')->nullOnDelete();
            $table->index(['type', 'status', 'created_at']);
            $table->index(['to_wallet_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
