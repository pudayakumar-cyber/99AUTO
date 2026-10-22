<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('promo_codes', function (Blueprint $table): void {
            $table->timestamp('expires_at')->nullable();
            $table->string('recipient_email')->nullable();
            $table->boolean('first_order_only')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('promo_codes', function (Blueprint $table): void {
            $table->dropColumn(['expires_at', 'recipient_email', 'first_order_only']);
        });
    }
};
