<?php

use App\Support\RainGear;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Für den Regenhinweis: Was schützt vor Regen? Bestehende Gegenstände nach Namen vorbelegen.
        Schema::table('items', function (Blueprint $table) {
            $table->boolean('rain_protection')->default(false)->after('pack_last');
        });

        $ids = DB::table('items')->pluck('name', 'id')->filter(fn (string $name) => RainGear::matches($name))->keys();
        DB::table('items')->whereIn('id', $ids)->update(['rain_protection' => true]);
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn('rain_protection');
        });
    }
};
