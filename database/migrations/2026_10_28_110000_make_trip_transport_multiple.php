<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** „Anreise mit“ wird Mehrfachauswahl, z. B. Flugzeug zur Insel und dann Mietwagen. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->json('transports')->nullable()->after('accommodation');
        });

        DB::table('trips')->whereNotNull('transport')->orderBy('id')->each(function ($trip) {
            DB::table('trips')->where('id', $trip->id)->update(['transports' => json_encode([$trip->transport])]);
        });

        Schema::table('trips', function (Blueprint $table) {
            $table->dropColumn('transport');
        });
    }

    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->string('transport', 32)->nullable()->after('accommodation');
        });

        DB::table('trips')->whereNotNull('transports')->orderBy('id')->each(function ($trip) {
            DB::table('trips')->where('id', $trip->id)->update(['transport' => json_decode($trip->transports, true)[0] ?? null]);
        });

        Schema::table('trips', fn (Blueprint $table) => $table->dropColumn('transports'));
    }
};
