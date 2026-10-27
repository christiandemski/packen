<x-layouts.app title="Vorlagen">
    <div class="mb-5">
        <p class="eyebrow">Module für deine Packlisten</p>
        <h1 class="page-title mt-1">Vorlagen</h1>
        <p class="mt-2 max-w-xl text-[15px] text-muted">Eine Packliste setzt sich aus mehreren Vorlagen zusammen, z. B. Basis + Städtetrip. Doppelte Gegenstände werden dabei zusammengeführt.</p>
    </div>

    @if ($starters)
        <form method="POST" action="{{ route('starter-templates.store') }}" class="card mb-6 border-accent/40 bg-accent-soft/40 p-5">
            @csrf
            <div class="flex items-start gap-3">
                <span class="inline-flex size-10 shrink-0 items-center justify-center rounded-chip bg-accent text-white"><x-icon name="sparkles" /></span>
                <div>
                    <p class="card-title">Mit Start-Vorlagen loslegen?</p>
                    <p class="mt-1 text-sm text-muted">Übernimm fertige Vorlagen samt Gegenständen in deinen Katalog. Danach kannst du alles anpassen.</p>
                </div>
            </div>
            <div class="mt-4 grid gap-2 sm:grid-cols-2">
                @foreach ($starters as $name => $starter)
                    <label class="flex cursor-pointer items-start gap-3 rounded-chip border border-line bg-surface p-3 has-checked:border-accent">
                        <input type="checkbox" name="templates[]" value="{{ $name }}" checked class="mt-0.5 size-5 accent-[var(--accent)]">
                        <span>
                            <span class="block text-[15px] font-bold">{{ $name }}</span>
                            <span class="block text-xs font-semibold text-muted">{{ $starter['description'] }} · {{ count($starter['items']) }} Gegenstände</span>
                        </span>
                    </label>
                @endforeach
            </div>
            @error('templates')<p class="field-error">{{ $message }}</p>@enderror
            <div class="mt-4 flex flex-wrap gap-2">
                <button type="submit" class="btn btn-primary"><x-icon name="check" size="18" /> Ausgewählte übernehmen</button>
                <button type="submit" form="dismiss-starters" class="btn btn-ghost">Nein danke</button>
            </div>
        </form>
        <form id="dismiss-starters" method="POST" action="{{ route('starter-templates.dismiss') }}" class="hidden">@csrf @method('DELETE')</form>
    @endif

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($templates as $template)
            <a href="{{ route('templates.show', $template) }}" class="card flex flex-col p-5 transition hover:-translate-y-0.5 hover:shadow-[0_10px_30px_-12px_rgba(23,33,30,0.25)]">
                <div class="flex items-start justify-between gap-3">
                    <h2 class="text-lg font-extrabold">{{ $template->name }}</h2>
                    <x-icon name="chevron-right" size="18" class="mt-1 text-muted" />
                </div>
                @if ($template->description)
                    <p class="mt-1 text-sm text-muted">{{ $template->description }}</p>
                @endif
                <p class="mt-3 flex flex-wrap gap-1.5">
                    @foreach ($template->items->take(5) as $item)
                        <span class="chip bg-line-soft text-muted">{{ $item->name }}</span>
                    @endforeach
                    @if ($template->items->count() > 5)
                        <span class="chip bg-line-soft text-muted">+{{ $template->items->count() - 5 }}</span>
                    @endif
                </p>
                <p class="mt-auto flex gap-4 border-t border-line-soft pt-3 text-xs font-bold text-muted [&:not(:first-child)]:mt-4">
                    <span>{{ $template->items->count() }} {{ $template->items->count() === 1 ? 'Gegenstand' : 'Gegenstände' }}</span>
                    @if ($template->totalWeight() > 0)
                        <span class="inline-flex items-center gap-1"><x-icon name="scale" size="13" /> {{ number_format($template->totalWeight() / 1000, 1, ',', '.') }} kg</span>
                    @endif
                </p>
            </a>
        @endforeach

        <form method="POST" action="{{ route('templates.store') }}" class="card flex flex-col gap-3 border-dashed p-5" x-data="{ open: {{ $errors->has('name') ? 'true' : 'false' }} }">
            @csrf
            <button type="button" x-show="!open" @click="open = true; $nextTick(() => $refs.name.focus())" class="flex min-h-24 flex-1 flex-col items-center justify-center gap-2 rounded-chip text-[15px] font-bold text-muted hover:text-ink">
                <x-icon name="plus" size="24" /> Neue Vorlage
            </button>
            <div x-show="open" x-cloak class="space-y-3">
                <div>
                    <label for="template-name" class="label">Name</label>
                    <input id="template-name" x-ref="name" name="name" value="{{ old('name') }}" required maxlength="80" placeholder="z. B. Winterurlaub" class="input">
                    @error('name')<p class="field-error">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="template-description" class="label">Beschreibung <span class="normal-case tracking-normal">(optional)</span></label>
                    <input id="template-description" name="description" value="{{ old('description') }}" maxlength="200" class="input">
                </div>
                <div class="flex gap-2">
                    <button type="submit" class="btn btn-primary">Anlegen</button>
                    <button type="button" class="btn btn-ghost" @click="open = false">Abbrechen</button>
                </div>
            </div>
        </form>
    </div>
</x-layouts.app>
