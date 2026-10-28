<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Link zurück in die anlegende App („unterwegs“), z. B. um Etappen dort zu ändern.
        Schema::table('trips', function (Blueprint $table) {
            $table->string('external_url', 255)->nullable()->after('external_id');
        });
    }

    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->dropColumn('external_url');
        });
    }
};
