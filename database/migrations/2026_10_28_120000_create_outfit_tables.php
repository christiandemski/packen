<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outfit_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('title', 120)->nullable();
            $table->boolean('laundry_day')->default(false);
            $table->timestamps();

            $table->unique(['trip_id', 'user_id', 'date']);
        });

        Schema::create('outfit_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outfit_day_id')->constrained()->cascadeOnDelete();
            $table->string('name', 40);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('outfit_slot_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outfit_slot_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();

            $table->unique(['outfit_slot_id', 'item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outfit_slot_items');
        Schema::dropIfExists('outfit_slots');
        Schema::dropIfExists('outfit_days');
    }
};
