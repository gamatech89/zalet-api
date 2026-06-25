<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }
        DB::statement('ALTER TABLE notifications DROP CONSTRAINT IF EXISTS notifications_type_check');
        DB::statement("ALTER TABLE notifications ADD CONSTRAINT notifications_type_check CHECK (type::text = ANY(ARRAY['follow','like','comment','gift','system','mention']::text[]))");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }
        DB::statement('ALTER TABLE notifications DROP CONSTRAINT IF EXISTS notifications_type_check');
        DB::statement("ALTER TABLE notifications ADD CONSTRAINT notifications_type_check CHECK (type::text = ANY(ARRAY['follow','like','comment','gift','system']::text[]))");
    }
};
