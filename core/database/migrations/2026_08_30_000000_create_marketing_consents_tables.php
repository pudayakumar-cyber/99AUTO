<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketing_consents', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('channel', 20);
            $table->string('identity', 255);
            $table->char('identity_hash', 64);
            $table->string('status', 20);
            $table->string('source', 100);
            $table->text('consent_text')->nullable();
            $table->timestamp('consented_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->string('last_ip', 45)->nullable();
            $table->text('last_user_agent')->nullable();
            $table->timestamps();

            $table->unique(['channel', 'identity_hash'], 'marketing_consents_channel_identity_unique');
            $table->index(['status', 'channel'], 'marketing_consents_status_channel_index');
        });

        Schema::create('marketing_consent_events', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('marketing_consent_id')->nullable()->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('channel', 20);
            $table->string('identity', 255);
            $table->char('identity_hash', 64);
            $table->string('action', 20);
            $table->string('source', 100);
            $table->text('consent_text')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['channel', 'identity_hash'], 'marketing_consent_events_identity_index');
            $table->index(['action', 'occurred_at'], 'marketing_consent_events_action_time_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_consent_events');
        Schema::dropIfExists('marketing_consents');
    }
};
