<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trip_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('template_id')->constrained()->cascadeOnDelete();

            $table->unique(['trip_id', 'user_id', 'template_id']);
        });

        Schema::create('packlist_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('custom_name', 120)->nullable();
            $table->string('group', 32)->nullable();        // nur für Freitext; sonst aus dem Gegenstand
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->boolean('quantity_locked')->default(false); // von Hand geändert → nicht neu berechnen
            $table->string('source', 16)->default('manual');
            $table->boolean('excluded')->default(false);    // aus Vorlage stammend, aber bewusst entfernt
            $table->string('note', 200)->nullable();
            $table->boolean('packed')->default(false);
            $table->timestamp('packed_at')->nullable();
            $table->timestamps();

            $table->unique(['trip_id', 'user_id', 'item_id']);
            $table->index(['trip_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('packlist_entries');
        Schema::dropIfExists('trip_templates');
    }
};
