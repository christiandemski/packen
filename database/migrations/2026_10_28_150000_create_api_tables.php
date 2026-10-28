<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Zugänge für andere Apps („unterwegs“); der Token selbst wird nur gehasht gespeichert.
        Schema::create('api_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 60);
            $table->string('token_hash', 64)->unique();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });

        // Etappen: Ort je Tag, z. B. bei einer Radreise.
        Schema::create('trip_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('location', 120);
            $table->timestamps();
            $table->unique(['trip_id', 'date']);
        });

        // Kennung der Reise in der anlegenden App, damit wiederholtes Anlegen nichts verdoppelt.
        Schema::table('trips', function (Blueprint $table) {
            $table->string('external_id', 64)->nullable()->after('owner_id');
            $table->unique(['owner_id', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->dropUnique(['owner_id', 'external_id']);
            $table->dropColumn('external_id');
        });
        Schema::dropIfExists('trip_stages');
        Schema::dropIfExists('api_tokens');
    }
};
