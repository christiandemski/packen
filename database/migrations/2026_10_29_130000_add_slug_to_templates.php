<?php

use App\Models\Template;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Lesbare Adressen: /vorlagen/basis statt /vorlagen/3 – eindeutig je Person
        Schema::table('templates', function (Blueprint $table) {
            $table->string('slug', 80)->nullable()->after('name');
            $table->unique(['owner_id', 'slug']);
        });

        // Frühere Adressen nach einer Umbenennung
        Schema::create('template_slugs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained()->cascadeOnDelete();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('slug', 80);

            $table->unique(['owner_id', 'slug']);
        });

        Template::query()->orderBy('id')->each(function (Template $template) {
            $template->slug = Template::uniqueSlug($template->name, $template->owner_id, $template->id);
            $template->saveQuietly();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('template_slugs');
        Schema::table('templates', function (Blueprint $table) {
            $table->dropUnique(['owner_id', 'slug']);
            $table->dropColumn('slug');
        });
    }
};
