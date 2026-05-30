<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('gift_catalog', function (Blueprint $table) {
            $table->foreignId('category_id')
                ->nullable()
                ->after('id')
                ->constrained('gift_categories')
                ->nullOnDelete();
            $table->string('icon_2d')->nullable()->after('icon_url');
            $table->string('icon_3d')->nullable()->after('icon_2d');
            $table->integer('sort_order')->default(0)->after('is_active');
            $table->text('description')->nullable()->after('sort_order');
            $table->string('icon_url')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('gift_catalog', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
            $table->dropColumn(['category_id', 'icon_2d', 'icon_3d', 'sort_order', 'description']);
        });
    }
};