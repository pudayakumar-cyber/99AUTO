<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('trade_review_decisions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('profile_id')->index();
            $table->string('reference', 100);
            $table->string('reviewer', 100);
            $table->string('previous_status', 30);
            $table->string('decision', 30);
            $table->timestamp('created_at');
            $table->unique(['profile_id', 'reference']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('trade_review_decisions');
    }
};
