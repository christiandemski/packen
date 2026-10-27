<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('containers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('kind', 16);
            $table->unsignedSmallInteger('capacity_l')->nullable();
            $table->unsignedInteger('max_weight_g')->nullable();
            $table->unsignedInteger('empty_weight_g')->nullable();
            $table->timestamps();
        });

        Schema::create('trip_containers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('container_id')->constrained()->cascadeOnDelete();

            $table->unique(['trip_id', 'user_id', 'container_id']);
        });

        Schema::table('items', function (Blueprint $table) {
            $table->foreignId('default_container_id')->nullable()->after('pack_last')->constrained('containers')->nullOnDelete();
        });

        Schema::table('packlist_entries', function (Blueprint $table) {
            $table->foreignId('container_id')->nullable()->after('group')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('packlist_entries', fn (Blueprint $table) => $table->dropConstrainedForeignId('container_id'));
        Schema::table('items', fn (Blueprint $table) => $table->dropConstrainedForeignId('default_container_id'));
        Schema::dropIfExists('trip_containers');
        Schema::dropIfExists('containers');
    }
};
