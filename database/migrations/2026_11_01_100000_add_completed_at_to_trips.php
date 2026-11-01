<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Reisen werden von Hand abgehakt und wandern dann nach „Zurückliegend“.
        Schema::table('trips', function (Blueprint $table) {
            $table->timestamp('completed_at')->nullable()->after('cover_path');
        });

        // Bisher galt eine Reise nach dem letzten Tag als vergangen – die bleiben dort.
        DB::table('trips')->whereDate('end_date', '<', now()->toDateString())->update(['completed_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('trips', fn (Blueprint $table) => $table->dropColumn('completed_at'));
    }
};
