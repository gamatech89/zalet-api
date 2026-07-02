<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') return;
        DB::statement('ALTER TABLE transactions DROP CONSTRAINT IF EXISTS transactions_type_check');
        DB::statement("ALTER TABLE transactions ADD CONSTRAINT transactions_type_check CHECK (type::text = ANY(ARRAY['deposit','tip','subscription','withdrawal','ppv','purchase','group_entry','stream_entry']::text[]))");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') return;
        DB::statement('ALTER TABLE transactions DROP CONSTRAINT IF EXISTS transactions_type_check');
        DB::statement("ALTER TABLE transactions ADD CONSTRAINT transactions_type_check CHECK (type::text = ANY(ARRAY['deposit','tip','subscription','withdrawal','ppv']::text[]))");
    }
};
