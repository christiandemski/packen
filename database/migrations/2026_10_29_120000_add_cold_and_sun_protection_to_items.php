<?php

use App\Enums\WeatherNeed;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Wie beim Regen: Hinweise bei Kälte und Sonne. Bestehende Gegenstände nach Namen vorbelegen.
        Schema::table('items', function (Blueprint $table) {
            $table->boolean('cold_protection')->default(false)->after('rain_protection');
            $table->boolean('sun_protection')->default(false)->after('cold_protection');
        });

        $names = DB::table('items')->pluck('name', 'id');
        foreach ([WeatherNeed::Cold, WeatherNeed::Sun] as $need) {
            $ids = $names->filter(fn (string $name) => $need->matches($name))->keys();
            DB::table('items')->whereIn('id', $ids)->update([$need->column() => true]);
        }
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn(['cold_protection', 'sun_protection']);
        });
    }
};
