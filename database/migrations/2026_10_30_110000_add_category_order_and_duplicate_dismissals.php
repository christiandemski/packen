<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Eigene Reihenfolge der Kategorien: Liste von Schlüsseln („tops“, „c:12“ …)
        Schema::table('users', function (Blueprint $table) {
            $table->json('category_order')->nullable()->after('push_settings');
        });

        // „Sind verschieden“ bei vermuteten Doppelungen im Katalog
        Schema::create('item_duplicate_dismissals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('item_a_id')->constrained('items')->cascadeOnDelete();
            $table->foreignId('item_b_id')->constrained('items')->cascadeOnDelete();

            $table->unique(['item_a_id', 'item_b_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_duplicate_dismissals');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('category_order'));
    }
};
