<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Ausschnitt des Titelbilds: Bildmittelpunkt in Prozent und Zoom in Prozent.
        // Kein fester Zuschnitt, weil Card und Reiseseite verschiedene Seitenverhältnisse haben.
        Schema::table('trips', function (Blueprint $table) {
            $table->unsignedTinyInteger('cover_focus_x')->default(50)->after('cover_path');
            $table->unsignedTinyInteger('cover_focus_y')->default(50)->after('cover_focus_x');
            $table->unsignedSmallInteger('cover_zoom')->default(100)->after('cover_focus_y');
        });
    }

    public function down(): void
    {
        Schema::table('trips', fn (Blueprint $table) => $table->dropColumn(['cover_focus_x', 'cover_focus_y', 'cover_zoom']));
    }
};
