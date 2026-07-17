<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->index(['status', 'slug'], 'items_status_slug_idx');
            $table->index(['status', 'category_id', 'id'], 'items_status_category_id_idx');
            $table->index(['status', 'subcategory_id', 'id'], 'items_status_subcategory_id_idx');
            $table->index(['status', 'childcategory_id', 'id'], 'items_status_childcategory_id_idx');
            $table->index(['status', 'brand_id', 'id'], 'items_status_brand_id_idx');
            $table->index(['status', 'discount_price', 'id'], 'items_status_price_id_idx');
            $table->index(['status', 'is_type', 'id'], 'items_status_type_id_idx');
        });

        Schema::table('attributes', function (Blueprint $table) {
            $table->index(['name', 'item_id'], 'attributes_name_item_id_idx');
            $table->index(['keyword', 'id'], 'attributes_keyword_id_idx');
        });

        Schema::table('attribute_options', function (Blueprint $table) {
            $table->index(['name', 'attribute_id'], 'attribute_options_name_attribute_id_idx');
            $table->index(['attribute_id', 'name'], 'attribute_options_attribute_name_idx');
        });
    }

    public function down(): void
    {
        Schema::table('attribute_options', function (Blueprint $table) {
            $table->dropIndex('attribute_options_attribute_name_idx');
            $table->dropIndex('attribute_options_name_attribute_id_idx');
        });

        Schema::table('attributes', function (Blueprint $table) {
            $table->dropIndex('attributes_keyword_id_idx');
            $table->dropIndex('attributes_name_item_id_idx');
        });

        Schema::table('items', function (Blueprint $table) {
            $table->dropIndex('items_status_type_id_idx');
            $table->dropIndex('items_status_price_id_idx');
            $table->dropIndex('items_status_brand_id_idx');
            $table->dropIndex('items_status_childcategory_id_idx');
            $table->dropIndex('items_status_subcategory_id_idx');
            $table->dropIndex('items_status_category_id_idx');
            $table->dropIndex('items_status_slug_idx');
        });
    }
};
