<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Meilenstein 5: Gruppen-Gegenstände, Anfragen, Packlisten-Freigabe, Einladung in eine Reise. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('group_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('note', 200)->nullable();
            $table->string('kind', 16);                      // planned | request
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 16)->default('open');   // open | claimed | withdrawn
            $table->timestamp('claimed_at')->nullable();
            // Eintrag in der Liste der fragenden Person, der nach dem Annehmen als erledigt gilt
            $table->foreignId('source_entry_id')->nullable()->constrained('packlist_entries')->nullOnDelete();
            $table->timestamps();

            $table->index(['trip_id', 'status']);
        });

        Schema::table('packlist_entries', function (Blueprint $table) {
            $table->foreignId('group_item_id')->nullable()->after('item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('packed_by')->nullable()->after('packed_changed_at')->constrained('users')->nullOnDelete();
        });

        Schema::create('packlist_grants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained()->cascadeOnDelete();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('grantee_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['trip_id', 'owner_id', 'grantee_id']);
        });

        Schema::table('invitations', function (Blueprint $table) {
            $table->foreignId('trip_id')->nullable()->after('invited_by')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('invitations', fn (Blueprint $table) => $table->dropConstrainedForeignId('trip_id'));
        Schema::dropIfExists('packlist_grants');
        Schema::table('packlist_entries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('packed_by');
            $table->dropConstrainedForeignId('group_item_id');
        });
        Schema::dropIfExists('group_items');
    }
};
