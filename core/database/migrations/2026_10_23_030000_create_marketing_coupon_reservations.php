<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketing_coupon_reservations', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('promo_code_id')->unique();
            $table->unsignedBigInteger('user_id')->index();
            $table->char('token_hash', 64)->unique();
            $table->unsignedBigInteger('order_id')->nullable()->unique();
            $table->timestamp('reserved_at');
            $table->timestamp('redeemed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_coupon_reservations');
    }
};
