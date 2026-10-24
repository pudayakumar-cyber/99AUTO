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
            $table->unsignedBigInteger('active_user_id')->nullable()->unique();
            $table->unsignedBigInteger('amount');
            $table->char('currency', 3);
            $table->char('snapshot_hash', 64);
            $table->longText('snapshot');
            $table->string('provider_intent_id')->nullable()->unique();
            $table->unsignedBigInteger('order_id')->nullable()->unique();
            $table->timestamp('finalized_at')->nullable();
            $table->timestamp('canceled_at')->nullable();
            $table->timestamps();
        });
        Schema::create('stripe_checkout_effects', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->string('topic', 30);
            $table->string('status', 20)->default('pending');
            $table->timestamps();
            $table->unique(['order_id', 'topic']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stripe_checkout_effects');
        Schema::dropIfExists('stripe_checkout_attempts');
    }
};
