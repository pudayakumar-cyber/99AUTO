<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('shipment_provider', 50)->nullable()->after('shipping');
            $table->string('shipping_method_code', 100)->nullable()->after('shipment_provider');
            $table->string('shipping_method_name', 255)->nullable()->after('shipping_method_code');
            $table->string('tracking_number', 100)->nullable()->after('shipping_method_name');
            $table->string('shipping_carrier', 150)->nullable()->after('tracking_number');
            $table->text('shipment_meta')->nullable()->after('shipping_carrier');
            $table->timestamp('shipment_created_at')->nullable()->after('shipment_meta');
            $table->timestamp('tracking_emailed_at')->nullable()->after('shipment_created_at');
        });
    }

    public function down()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'shipment_provider',
                'shipping_method_code',
                'shipping_method_name',
                'tracking_number',
                'shipping_carrier',
                'shipment_meta',
                'shipment_created_at',
                'tracking_emailed_at',
            ]);
        });
    }
};
