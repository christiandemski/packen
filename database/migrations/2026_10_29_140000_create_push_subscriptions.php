<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Web Push je Gerät (Browser), für die Erinnerung am Abfahrtstag
        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('endpoint');
            $table->char('endpoint_hash', 64)->unique();
            $table->string('user_agent', 200)->nullable();
            $table->timestamps();
        });

        // Damit die Erinnerung je Reise und Person nur einmal rausgeht
        Schema::table('trip_members', function (Blueprint $table) {
            $table->date('departure_reminded_on')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('trip_members', fn (Blueprint $table) => $table->dropColumn('departure_reminded_on'));
        Schema::dropIfExists('push_subscriptions');
    }
};
