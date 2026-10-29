@php use App\Models\Category; @endphp
<x-layouts.app title="Kategorien">
    <div class="mx-auto max-w-2xl">
        <div class="mb-5 flex items-start gap-2">
            <a href="{{ route('items.index') }}" class="icon-btn -ml-2" aria-label="Zurück zum Katalog"><x-icon name="arrow-left" /></a>
            <div>
                <h1 class="page-title">Kategorien</h1>
                <p class="mt-2 text-[15px] text-muted">Eigene Kategorien zusätzlich zu den festen, z. B. „Kamera“ oder „Hund“. In der Packliste bekommt jede eine eigene Karte, direkt vor „Sonstiges“.</p>
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

        <section class="mt-10">
            <h2 class="card-title">Feste Kategorien</h2>
            <p class="mt-1 mb-3 text-sm text-muted">Umbenennen oder ausblenden, was du nie brauchst – nur für dich. Ausgeblendete stehen nicht mehr zur Auswahl; Gegenstände darin bleiben sichtbar.</p>
            <div class="card divide-y divide-line-soft">
                @foreach ($builtin as ['category' => $case, 'hidden' => $hidden, 'renamed' => $renamed])
                    @php($bag = 'builtin-'.$case->value)
                    <div x-data="{ edit: {{ $errors->getBag($bag)->any() ? 'true' : 'false' }} }">
                        <div class="flex min-h-[56px] items-center gap-3 py-2 pr-2 pl-5 {{ $hidden ? 'text-muted' : '' }}">
                            <x-icon :name="$case->icon()" size="18" class="{{ $hidden ? '' : 'text-accent' }}" />
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-[15px] font-bold {{ $hidden ? 'line-through' : '' }}">{{ $case->label() }}</p>
                                @if ($renamed || $hidden)
                                    <p class="text-xs font-semibold text-muted">{{ collect([$renamed ? 'statt „'.$case->defaultLabel().'“' : null, $hidden ? 'ausgeblendet' : null])->filter()->join(' · ') }}</p>
                                @endif
                            </div>
                            <button type="button" class="icon-btn" @click="edit = !edit" :aria-expanded="edit" aria-label="{{ $case->label() }} bearbeiten"><x-icon name="pencil" size="18" /></button>
                        </div>
                        <form method="POST" action="{{ route('categories.builtin.update', $case->value) }}" x-show="edit" x-cloak class="space-y-3 bg-line-soft/60 px-5 py-4">
                            @csrf @method('PUT')
                            <div>
                                <label for="label-{{ $case->value }}" class="label">Name</label>
                                <input id="label-{{ $case->value }}" name="label" value="{{ $errors->getBag($bag)->any() ? old('label') : ($renamed ? $case->label() : '') }}" maxlength="60" placeholder="{{ $case->defaultLabel() }}" class="input">
                                @error('label', $bag)<p class="field-error">{{ $message }}</p>@enderror
                                <p class="mt-1.5 text-xs font-semibold text-muted">Leer lassen für „{{ $case->defaultLabel() }}“.</p>
                            </div>
                            @if ($case->canBeHidden())
                                <label class="flex cursor-pointer items-center gap-3 text-sm font-bold">
                                    <input type="checkbox" name="hidden" value="1" @checked($hidden) class="size-5 accent-[var(--accent)]"> Ausblenden
                                </label>
                            @else
                                <p class="text-xs font-semibold text-muted">„Sonstiges“ bleibt immer sichtbar – dort landet alles ohne Kategorie.</p>
                            @endif
                            <button type="submit" class="btn btn-primary">Speichern</button>
                        </form>
                    </div>
                @endforeach
            </div>
        </section>
    </div>
</x-layouts.app>
