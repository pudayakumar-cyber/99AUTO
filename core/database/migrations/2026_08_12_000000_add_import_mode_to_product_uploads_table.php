<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_uploads', function (Blueprint $table): void {
            $table->string('import_mode', 20)->default('create')->after('file_path');
            $table->index(['import_mode', 'created_at'], 'product_uploads_mode_created_index');
        });
    }

    public function down(): void
    {
        Schema::table('product_uploads', function (Blueprint $table): void {
            $table->dropIndex('product_uploads_mode_created_index');
            $table->dropColumn('import_mode');
        });
    }
};
