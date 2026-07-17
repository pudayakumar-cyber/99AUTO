<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_fitments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->string('year', 4)->index();
            $table->string('make')->index();
            $table->string('model')->index();
            $table->string('raw_make')->nullable();
            $table->string('raw_model')->nullable();
            $table->timestamps();

            $table->unique(['item_id', 'year', 'make', 'model'], 'item_fitments_unique_fitment');
            $table->index(['year', 'make', 'model', 'item_id'], 'item_fitments_vehicle_lookup');
            $table->index(['item_id', 'year'], 'item_fitments_item_year');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_fitments');
    }
};
