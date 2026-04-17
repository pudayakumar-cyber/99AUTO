<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('items', function (Blueprint $table) {
            $table->decimal('package_length', 10, 2)->nullable()->after('stock');
            $table->decimal('package_width', 10, 2)->nullable()->after('package_length');
            $table->decimal('package_height', 10, 2)->nullable()->after('package_width');
            $table->decimal('package_weight', 10, 2)->nullable()->after('package_height');
        });
    }

    public function down()
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn(['package_length', 'package_width', 'package_height', 'package_weight']);
        });
    }
};
