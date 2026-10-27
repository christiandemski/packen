<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->date('start_date');
            $table->date('end_date');
            $table->string('type', 32);
            $table->string('mode', 16)->default('solo');
            $table->string('accommodation', 32)->nullable();
            $table->string('transport', 32)->nullable();
            $table->string('location')->nullable();
            $table->string('cover_path')->nullable();
            $table->timestamps();
        });

        Schema::create('trip_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 16)->default('member');
            $table->boolean('outfits_shared')->default(false);
            $table->timestamp('joined_at')->nullable();

            $table->unique(['trip_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_members');
        Schema::dropIfExists('trips');
    }
};
