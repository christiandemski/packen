<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Rückreise-Check: zweiter Haken „wieder eingepackt“ (letzter Stand gewinnt, wie beim Packen)
        Schema::table('packlist_entries', function (Blueprint $table) {
            $table->boolean('returned')->default(false)->after('packed_changed_at');
            $table->timestamp('returned_changed_at')->nullable()->after('returned');
        });

        // Welche Erinnerung (Vorabend, Abfahrtstag, Rückblick) ging schon raus – je Reise und Person einmal
        Schema::create('reminders_sent', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 16);
            $table->timestamp('sent_at');

            $table->unique(['trip_id', 'user_id', 'kind']);
        });

        Schema::table('trip_members', fn (Blueprint $table) => $table->dropColumn('departure_reminded_on'));

        // Welche Push-Nachrichten jemand möchte (fehlender Schlüssel = ja)
        Schema::table('users', function (Blueprint $table) {
            $table->json('push_settings')->nullable()->after('email_notifications');
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('push_settings'));
        Schema::table('trip_members', fn (Blueprint $table) => $table->date('departure_reminded_on')->nullable());
        Schema::dropIfExists('reminders_sent');
        Schema::table('packlist_entries', fn (Blueprint $table) => $table->dropColumn(['returned', 'returned_changed_at']));
    }
};
