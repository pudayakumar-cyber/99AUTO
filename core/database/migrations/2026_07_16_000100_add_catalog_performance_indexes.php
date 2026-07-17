<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->addIndex('items', 'items_status_slug_idx', 'ALTER TABLE `items` ADD INDEX `items_status_slug_idx` (`status`, `slug`(191))');
        $this->addIndex('items', 'items_status_category_id_idx', 'ALTER TABLE `items` ADD INDEX `items_status_category_id_idx` (`status`, `category_id`, `id`)');
        $this->addIndex('items', 'items_status_subcategory_id_idx', 'ALTER TABLE `items` ADD INDEX `items_status_subcategory_id_idx` (`status`, `subcategory_id`, `id`)');
        $this->addIndex('items', 'items_status_childcategory_id_idx', 'ALTER TABLE `items` ADD INDEX `items_status_childcategory_id_idx` (`status`, `childcategory_id`, `id`)');
        $this->addIndex('items', 'items_status_brand_id_idx', 'ALTER TABLE `items` ADD INDEX `items_status_brand_id_idx` (`status`, `brand_id`, `id`)');
        $this->addIndex('items', 'items_status_price_id_idx', 'ALTER TABLE `items` ADD INDEX `items_status_price_id_idx` (`status`, `discount_price`, `id`)');
        $this->addIndex('items', 'items_status_type_id_idx', 'ALTER TABLE `items` ADD INDEX `items_status_type_id_idx` (`status`, `is_type`, `id`)');

        $this->addIndex('attributes', 'attributes_name_item_id_idx', 'ALTER TABLE `attributes` ADD INDEX `attributes_name_item_id_idx` (`name`(191), `item_id`)');
        $this->addIndex('attributes', 'attributes_keyword_id_idx', 'ALTER TABLE `attributes` ADD INDEX `attributes_keyword_id_idx` (`keyword`(191), `id`)');

        $this->addIndex('attribute_options', 'attribute_options_name_attribute_id_idx', 'ALTER TABLE `attribute_options` ADD INDEX `attribute_options_name_attribute_id_idx` (`name`(191), `attribute_id`)');
        $this->addIndex('attribute_options', 'attribute_options_attribute_name_idx', 'ALTER TABLE `attribute_options` ADD INDEX `attribute_options_attribute_name_idx` (`attribute_id`, `name`(191))');
    }

    public function down(): void
    {
        $this->dropIndex('attribute_options', 'attribute_options_attribute_name_idx');
        $this->dropIndex('attribute_options', 'attribute_options_name_attribute_id_idx');

        $this->dropIndex('attributes', 'attributes_keyword_id_idx');
        $this->dropIndex('attributes', 'attributes_name_item_id_idx');

        $this->dropIndex('items', 'items_status_type_id_idx');
        $this->dropIndex('items', 'items_status_price_id_idx');
        $this->dropIndex('items', 'items_status_brand_id_idx');
        $this->dropIndex('items', 'items_status_childcategory_id_idx');
        $this->dropIndex('items', 'items_status_subcategory_id_idx');
        $this->dropIndex('items', 'items_status_category_id_idx');
        $this->dropIndex('items', 'items_status_slug_idx');
    }

    private function addIndex(string $table, string $index, string $statement): void
    {
        if (!$this->indexExists($table, $index)) {
            DB::statement($statement);
        }
    }

    private function dropIndex(string $table, string $index): void
    {
        if ($this->indexExists($table, $index)) {
            DB::statement("ALTER TABLE `{$table}` DROP INDEX `{$index}`");
        }
    }

    private function indexExists(string $table, string $index): bool
    {
        return (bool) DB::table('information_schema.statistics')
            ->where('table_schema', DB::raw('DATABASE()'))
            ->where('table_name', $table)
            ->where('index_name', $index)
            ->exists();
    }
};
