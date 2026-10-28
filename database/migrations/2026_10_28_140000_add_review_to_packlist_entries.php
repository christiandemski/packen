<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('packlist_entries', function (Blueprint $table) {
            // Rückblick: unused = nicht gebraucht / nicht getragen, missing = hat gefehlt
            $table->string('review', 16)->nullable()->after('packed_by');
        });
    }

    public function down(): void
    {
        Schema::table('packlist_entries', fn (Blueprint $table) => $table->dropColumn('review'));
    }
};
