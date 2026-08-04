<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table): void {
            $table->index('sku', 'items_sku_import_index');
            $table->index('prod_number', 'items_prod_number_import_index');
            $table->index('product_part_number', 'items_product_part_number_import_index');
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table): void {
            $table->dropIndex('items_sku_import_index');
            $table->dropIndex('items_prod_number_import_index');
            $table->dropIndex('items_product_part_number_import_index');
        });
    }
};
