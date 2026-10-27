<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('packlist_entries', function (Blueprint $table) {
            // Zeitpunkt der letzten Abhak-Änderung laut Gerät – für „letzter Stand gewinnt“ beim Offline-Abgleich.
            $table->timestamp('packed_changed_at')->nullable()->after('packed_at');
        });
    }

    public function down(): void
    {
        Schema::table('packlist_entries', fn (Blueprint $table) => $table->dropColumn('packed_changed_at'));
    }
};
