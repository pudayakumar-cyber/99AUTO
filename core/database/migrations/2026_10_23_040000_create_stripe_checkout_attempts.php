<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('stripe_checkout_attempts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('amount');
            $table->char('currency', 3);
            $table->char('snapshot_hash', 64);
            $table->longText('snapshot');
            $table->string('provider_intent_id')->nullable()->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stripe_checkout_attempts');
    }
};
