<?php

declare(strict_types=1);

use App\Domains\Wallet\Models\PaymentIntent;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_intents', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 30)->default('raiaccept');
            $table->string('provider_order_id', 255)->nullable();
            $table->text('provider_session_url')->nullable();
            $table->bigInteger('amount_cents');
            $table->bigInteger('credits_amount');
            $table->char('currency', 3)->default('EUR');
            $table->string('status', 30)->default(PaymentIntent::STATUS_PENDING);
            $table->string('idempotency_key', 64)->unique();
            $table->timestamp('webhook_received_at')->nullable();
            $table->jsonb('meta')->default('{}');
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('provider_order_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_intents');
    }
};
