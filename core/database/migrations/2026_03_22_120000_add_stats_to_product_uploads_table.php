<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_uploads', function (Blueprint $table) {
            $table->unsignedInteger('imported_count')->default(0)->after('processed_rows');
            $table->unsignedInteger('skipped_count')->default(0)->after('imported_count');
            $table->text('error_message')->nullable()->after('skipped_count');
        });
    }

    public function down(): void
    {
        Schema::table('product_uploads', function (Blueprint $table) {
            $table->dropColumn(['imported_count', 'skipped_count', 'error_message']);
        });
    }
};
