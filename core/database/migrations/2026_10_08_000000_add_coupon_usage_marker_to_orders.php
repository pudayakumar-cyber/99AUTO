<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            foreach (['orders', 'promo_codes'] as $table) {
                $metadata = DB::selectOne(
                    'SELECT ENGINE AS engine FROM information_schema.tables WHERE table_schema = ? AND table_name = ?',
                    [DB::connection()->getDatabaseName(), DB::connection()->getTablePrefix().$table]
                );
                if (strtolower((string) ($metadata->engine ?? '')) !== 'innodb') {
                    throw new RuntimeException('Coupon usage accounting requires InnoDB: '.$table);
                }
            }
        }

        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('coupon_usage_recorded_at')->nullable();
        });

        // Preserve historical balances. Old finalized orders were already handled
        // by legacy payment code; do not charge their usage again on admin updates.
        DB::table('orders')->where('transaction_number', 'like', 'ORD-%')
            ->update(['coupon_usage_recorded_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('coupon_usage_recorded_at');
        });
    }
};
