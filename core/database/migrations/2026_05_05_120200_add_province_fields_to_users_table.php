<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'bill_province')) {
                $table->string('bill_province', 50)->nullable()->after('bill_city');
            }
            if (!Schema::hasColumn('users', 'ship_province')) {
                $table->string('ship_province', 50)->nullable()->after('ship_city');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $drop = [];
            foreach (['bill_province', 'ship_province'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $drop[] = $column;
                }
            }
            if ($drop) {
                $table->dropColumn($drop);
            }
        });
    }
};
