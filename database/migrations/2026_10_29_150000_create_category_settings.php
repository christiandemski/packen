<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Feste Kategorien je Person umbenennen oder ausblenden
        Schema::create('category_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('category', 32);           // App\Enums\ItemCategory
            $table->string('label', 60)->nullable();  // null = Standardname
            $table->boolean('hidden')->default(false);
            $table->timestamps();

            $table->unique(['owner_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_settings');
    }
};
