<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->bigInteger('balance')->default(0);
            $table->string('currency', 10)->default('CREDITS');
            $table->timestamps();
        });

        // Add CHECK constraint for PostgreSQL to prevent negative balances
        DB::statement('ALTER TABLE wallets ADD CONSTRAINT wallets_balance_positive CHECK (balance >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('wallets');
    }
};
