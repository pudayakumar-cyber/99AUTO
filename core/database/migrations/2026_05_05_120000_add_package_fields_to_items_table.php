<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            if (!Schema::hasColumn('items', 'package_length')) {
                $table->decimal('package_length', 10, 2)->nullable()->after('stock');
            }
            if (!Schema::hasColumn('items', 'package_width')) {
                $table->decimal('package_width', 10, 2)->nullable()->after('package_length');
            }
            if (!Schema::hasColumn('items', 'package_height')) {
                $table->decimal('package_height', 10, 2)->nullable()->after('package_width');
            }
            if (!Schema::hasColumn('items', 'package_weight')) {
                $table->decimal('package_weight', 10, 2)->nullable()->after('package_height');
            }
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $drop = [];
            foreach (['package_length', 'package_width', 'package_height', 'package_weight'] as $column) {
                if (Schema::hasColumn('items', $column)) {
                    $drop[] = $column;
                }
            }
            if ($drop) {
                $table->dropColumn($drop);
            }
        });
    }
};
