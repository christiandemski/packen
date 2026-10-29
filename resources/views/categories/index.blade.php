@php use App\Models\Category; @endphp
<x-layouts.app title="Kategorien">
    <div class="mx-auto max-w-2xl">
        <div class="mb-5 flex items-start gap-2">
            <a href="{{ route('items.index') }}" class="icon-btn -ml-2" aria-label="Zurück zum Katalog"><x-icon name="arrow-left" /></a>
            <div>
                <h1 class="page-title">Eigene Kategorien</h1>
                <p class="mt-2 text-[15px] text-muted">Zusätzlich zu den festen Kategorien, z. B. „Kamera“ oder „Hund“. In der Packliste bekommt jede eine eigene Karte, direkt vor „Sonstiges“.</p>
            </div>
        </div>

        <div class="space-y-3">
            @foreach ($categories as $category)
                @php($bag = 'edit-'.$category->id)
                <div class="card" x-data="{ edit: {{ $errors->getBag($bag)->any() ? 'true' : 'false' }} }">
                    <div class="flex min-h-[64px] items-center gap-3 px-5 py-3">
                        <span class="inline-flex size-10 shrink-0 items-center justify-center rounded-chip bg-accent-soft text-accent-ink"><x-icon :name="$category->icon" /></span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-[15px] font-bold">{{ $category->name }}</p>
                            <p class="text-xs font-semibold text-muted">{{ $category->items_count }} {{ $category->items_count === 1 ? 'Gegenstand' : 'Gegenstände' }}</p>
                        </div>
                        <button type="button" class="icon-btn" @click="edit = !edit" :aria-expanded="edit" aria-label="{{ $category->name }} bearbeiten"><x-icon name="pencil" size="18" /></button>
                    </div>
                    <div x-show="edit" x-cloak class="border-t border-line-soft px-5 py-4" x-data="{ confirming: false }">
                        @include('categories._form', ['category' => $category, 'bag' => $bag])
                        <div class="mt-4 border-t border-line-soft pt-4">
                            <button type="button" class="btn btn-danger min-h-10 text-sm" x-show="!confirming" @click="confirming = true"><x-icon name="trash" size="16" /> Löschen</button>
                            <form method="POST" action="{{ route('categories.destroy', $category) }}" x-show="confirming" x-cloak class="flex flex-wrap items-center gap-2">
                                @csrf @method('DELETE')
                                <p class="w-full text-sm text-muted">Die Gegenstände bleiben im Katalog und stehen danach unter „Sonstiges“.</p>
                                <button type="submit" class="btn bg-warn text-white hover:bg-warn-ink min-h-10 text-sm">Ja, löschen</button>
                                <button type="button" class="btn btn-secondary min-h-10 text-sm" @click="confirming = false">Abbrechen</button>
                            </form>
                        </div>
                    </div>
                </div>
            @endforeach

            <div class="card border-dashed p-5" x-data="{ open: {{ $errors->getBag('create')->any() || $categories->isEmpty() ? 'true' : 'false' }} }">
                <button type="button" x-show="!open" @click="open = true" class="flex min-h-12 w-full items-center justify-center gap-2 text-[15px] font-bold text-muted hover:text-ink">
                    <x-icon name="plus" size="20" /> Kategorie anlegen
                </button>
                <div x-show="open" x-cloak>
                    <p class="card-title mb-3">Neue Kategorie</p>
                    @include('categories._form', ['category' => new Category(['icon' => 'package']), 'bag' => 'create'])
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>
