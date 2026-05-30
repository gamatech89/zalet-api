<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // 1. Board categories table
        Schema::create('board_categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('slug')->unique();
            $table->string('name_en');
            $table->string('name_sr');
            $table->string('icon')->nullable(); // lucide icon name
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->uuid('board_id')->nullable(); // null = global category
            $table->timestamps();

            $table->foreign('board_id')->references('id')->on('boards')->cascadeOnDelete();
            $table->index(['board_id', 'sort_order']);
        });

        // Seed default global categories
        $now = now();
        $categories = [
            ['id' => \Illuminate\Support\Str::uuid(), 'slug' => 'apartment', 'name_en' => 'Apartment', 'name_sr' => 'Stan', 'icon' => 'Home', 'sort_order' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['id' => \Illuminate\Support\Str::uuid(), 'slug' => 'job', 'name_en' => 'Job', 'name_sr' => 'Posao', 'icon' => 'Briefcase', 'sort_order' => 2, 'created_at' => $now, 'updated_at' => $now],
            ['id' => \Illuminate\Support\Str::uuid(), 'slug' => 'roommate', 'name_en' => 'Roommate', 'name_sr' => 'Cimer', 'icon' => 'Users', 'sort_order' => 3, 'created_at' => $now, 'updated_at' => $now],
            ['id' => \Illuminate\Support\Str::uuid(), 'slug' => 'ride', 'name_en' => 'Ride', 'name_sr' => 'Prevoz', 'icon' => 'Car', 'sort_order' => 4, 'created_at' => $now, 'updated_at' => $now],
            ['id' => \Illuminate\Support\Str::uuid(), 'slug' => 'advice', 'name_en' => 'Advice', 'name_sr' => 'Savet', 'icon' => 'Lightbulb', 'sort_order' => 5, 'created_at' => $now, 'updated_at' => $now],
            ['id' => \Illuminate\Support\Str::uuid(), 'slug' => 'general', 'name_en' => 'General', 'name_sr' => 'Opšte', 'icon' => 'LayoutGrid', 'sort_order' => 6, 'created_at' => $now, 'updated_at' => $now],
        ];
        DB::table('board_categories')->insert($categories);

        // 2. Board members table (governance)
        Schema::create('board_members', function (Blueprint $table) {
            $table->uuid('board_id');
            $table->uuid('user_id');
            $table->enum('role', ['admin', 'moderator', 'member'])->default('member');
            $table->timestamps();

            $table->primary(['board_id', 'user_id']);
            $table->foreign('board_id')->references('id')->on('boards')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->index(['user_id', 'role']);
        });

        // 3. Alter board_posts: category enum → string, add place_id
        // SQLite stores enums as text already, so only run ALTER on PostgreSQL
        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'pgsql') {
            DB::statement("ALTER TABLE board_posts ALTER COLUMN category TYPE VARCHAR(50)");
            DB::statement("ALTER TABLE board_posts ALTER COLUMN type TYPE VARCHAR(50)");
        }

        Schema::table('board_posts', function (Blueprint $table) {
            $table->uuid('place_id')->nullable()->after('location_label');
            $table->foreign('place_id')->references('id')->on('places')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('board_posts', function (Blueprint $table) {
            $table->dropForeign(['place_id']);
            $table->dropColumn('place_id');
        });

        // Restore enums
        DB::statement("ALTER TABLE board_posts ALTER COLUMN category TYPE VARCHAR(50)");
        DB::statement("ALTER TABLE board_posts ALTER COLUMN type TYPE VARCHAR(50)");

        Schema::dropIfExists('board_members');
        Schema::dropIfExists('board_categories');
    }
};
