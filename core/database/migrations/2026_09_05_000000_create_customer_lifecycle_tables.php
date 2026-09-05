<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_lifecycle_profiles', function (Blueprint $table): void {
            $table->id();
            $table->char('identity_hash', 64)->unique();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('email')->nullable()->index();
            $table->string('phone', 40)->nullable();
            $table->string('first_name', 100)->nullable();
            $table->string('last_name', 100)->nullable();
            $table->string('address1')->nullable();
            $table->string('address2')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('region', 100)->nullable();
            $table->string('country', 100)->nullable();
            $table->string('postal_code', 30)->nullable();
            $table->string('buyer_type', 30)->default('DIY');
            $table->string('lifecycle_status', 30)->default('prospect');
            $table->unsignedSmallInteger('primary_vehicle_year')->nullable();
            $table->string('primary_vehicle_make', 100)->nullable();
            $table->string('primary_vehicle_model', 100)->nullable();
            $table->string('last_purchase_category', 100)->nullable();
            $table->timestamp('last_purchase_at')->nullable();
            $table->date('next_maintenance_due_date')->nullable();
            $table->string('referral_code', 50)->nullable()->unique();
            $table->string('referral_status', 30)->default('inactive');
            $table->string('trade_review_status', 30)->default('not_requested');
            $table->unsignedInteger('total_orders')->default(0);
            $table->timestamps();
        });

        Schema::create('customer_lifecycle_orders', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('customer_lifecycle_profile_id')->index();
            $table->unsignedBigInteger('order_id')->unique();
            $table->string('purchase_category', 100);
            $table->unsignedSmallInteger('maintenance_interval_days');
            $table->date('maintenance_due_date');
            $table->timestamp('purchased_at');
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();

            $table->index(
                ['customer_lifecycle_profile_id', 'purchased_at'],
                'customer_lifecycle_orders_profile_purchase_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_lifecycle_orders');
        Schema::dropIfExists('customer_lifecycle_profiles');
    }
};
