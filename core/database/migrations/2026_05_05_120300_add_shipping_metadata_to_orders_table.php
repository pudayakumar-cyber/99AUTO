<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'shipment_provider')) {
                $table->string('shipment_provider', 50)->nullable()->after('shipping');
            }
            if (!Schema::hasColumn('orders', 'shipping_method_code')) {
                $table->string('shipping_method_code', 100)->nullable()->after('shipment_provider');
            }
            if (!Schema::hasColumn('orders', 'shipping_method_name')) {
                $table->string('shipping_method_name', 255)->nullable()->after('shipping_method_code');
            }
            if (!Schema::hasColumn('orders', 'shipping_carrier')) {
                $table->string('shipping_carrier', 150)->nullable()->after('tracking_number');
            }
            if (!Schema::hasColumn('orders', 'shipment_meta')) {
                $table->text('shipment_meta')->nullable()->after('shipping_carrier');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $drop = [];
            foreach (['shipment_provider', 'shipping_method_code', 'shipping_method_name', 'shipping_carrier', 'shipment_meta'] as $column) {
                if (Schema::hasColumn('orders', $column)) {
                    $drop[] = $column;
                }
            }
            if ($drop) {
                $table->dropColumn($drop);
            }
        });
    }
};
