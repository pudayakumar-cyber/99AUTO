<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('orders', 'tracking_number')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->string('tracking_number')->nullable()->after('transaction_number');
            });
        }

        if (Schema::hasTable('email_templates') && ! DB::table('email_templates')->where('type', 'Order Tracking Update')->exists()) {
            DB::table('email_templates')->insert([
                'type' => 'Order Tracking Update',
                'subject' => 'Tracking Number for Order {transaction_number}',
                'body' => '<p>Hello {user_name},</p><p>Your tracking number for order <strong>{transaction_number}</strong> is <strong>{tracking_number}</strong>.</p><p>Order Total: {order_cost}</p><p>Current Order Status: {order_status}</p>',
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('orders', 'tracking_number')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropColumn('tracking_number');
            });
        }
    }
};
