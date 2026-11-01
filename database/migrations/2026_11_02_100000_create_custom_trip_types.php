<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Eigene Reisearten zusätzlich zu den festen (App\Enums\TripType).
        Schema::create('custom_trip_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('name', 60);
            $table->string('icon', 32);
            $table->string('color', 16);
            $table->timestamps();

            $table->unique(['owner_id', 'name']);
        });

        // Gesetzt → eigene Reiseart; „type“ steht dann auf „other“ (Rückfall beim Löschen).
        Schema::table('trips', function (Blueprint $table) {
            $table->foreignId('custom_trip_type_id')->nullable()->after('type')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('trips', fn (Blueprint $table) => $table->dropConstrainedForeignId('custom_trip_type_id'));
        Schema::dropIfExists('custom_trip_types');
    }
};
