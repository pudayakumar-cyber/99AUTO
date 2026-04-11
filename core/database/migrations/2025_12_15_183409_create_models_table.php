<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('models', function (Blueprint $table) {
            $table->id();
            $table->foreignId('year_id')
                ->constrained('years')
                ->cascadeOnDelete();
            $table->foreignId('make_id')
                ->constrained('makes')
                ->cascadeOnDelete();
            $table->string('model')->nullable();
            $table->string('bodyType')->nullable();
            $table->timestamps();
            $table->unique(['year_id', 'make_id', 'model']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('models');
    }
};
