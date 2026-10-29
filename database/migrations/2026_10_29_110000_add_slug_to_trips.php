<?php

use App\Models\Trip;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Lesbare Adressen: /reisen/mallorca statt /reisen/12
        Schema::table('trips', function (Blueprint $table) {
            $table->string('slug', 80)->nullable()->unique()->after('name');
        });

        // Frühere Adressen nach einer Umbenennung, damit alte Links weiter funktionieren.
        Schema::create('trip_slugs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained()->cascadeOnDelete();
            $table->string('slug', 80)->unique();
        });

        Trip::query()->orderBy('start_date')->orderBy('id')->each(function (Trip $trip) {
            $trip->slug = Trip::uniqueSlug($trip->name, $trip->start_date, $trip->id);
            $trip->saveQuietly();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_slugs');
        Schema::table('trips', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn('slug');
        });
    }
};
