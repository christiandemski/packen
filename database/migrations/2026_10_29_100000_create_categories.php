<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Eigene Kategorien zusätzlich zu den festen (App\Enums\ItemCategory).
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('name', 60);
            $table->string('icon', 32);
            $table->timestamps();

            $table->unique(['owner_id', 'name']);
        });

        // Gesetzt → eigene Kategorie; die feste Spalte „category“ steht dann auf „misc“ (Rückfall beim Löschen).
        Schema::table('items', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->after('category')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('category_id');
        });
        Schema::dropIfExists('categories');
    }
};
