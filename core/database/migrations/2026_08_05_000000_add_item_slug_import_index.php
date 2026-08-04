<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE INDEX items_slug_import_index ON items (slug(191))');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX items_slug_import_index ON items');
    }
};
