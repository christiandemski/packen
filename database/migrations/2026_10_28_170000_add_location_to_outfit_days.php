<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Eigener Ort für einen Tag (z. B. Tagesausflug) – persönlich, überschreibt Etappe und Reiseziel.
        Schema::table('outfit_days', function (Blueprint $table) {
            $table->string('location', 120)->nullable()->after('title');
        });
    }

    public function down(): void
    {
        Schema::table('outfit_days', function (Blueprint $table) {
            $table->dropColumn('location');
        });
    }
};
