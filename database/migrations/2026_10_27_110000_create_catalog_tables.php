<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('category', 32);
            $table->unsignedInteger('weight_g')->nullable();
            $table->boolean('is_clothing')->default(false);
            $table->boolean('per_day')->default(false);
            $table->boolean('pack_last')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['owner_id', 'category']);
        });

        Schema::create('templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('description', 200)->nullable();
            $table->timestamps();
        });

        Schema::create('template_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('quantity')->default(1);

            $table->unique(['template_id', 'item_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('starter_templates_at')->nullable()->after('email_notifications');
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('starter_templates_at'));
        Schema::dropIfExists('template_items');
        Schema::dropIfExists('templates');
        Schema::dropIfExists('items');
    }
};
