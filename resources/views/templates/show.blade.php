@php use App\Http\Controllers\TemplateItemController; use Illuminate\Support\Str; @endphp
<x-layouts.app :title="$template->name">
    <div class="mx-auto max-w-2xl">
        <div class="mb-5 flex items-start gap-2" x-data="{ renaming: {{ $errors->has('name') ? 'true' : 'false' }} }">
            <a href="{{ route('templates.index') }}" class="icon-btn -ml-2" aria-label="Zurück zu den Vorlagen"><x-icon name="arrow-left" /></a>
            <div class="min-w-0 flex-1" x-show="!renaming">
                <h1 class="page-title">{{ $template->name }}</h1>
                <p class="mt-1.5 text-[15px] font-semibold text-muted">
                    {{ $template->items->count() }} {{ $template->items->count() === 1 ? 'Gegenstand' : 'Gegenstände' }}
                    @if ($template->totalWeight() > 0) · {{ number_format($template->totalWeight() / 1000, 2, ',', '.') }} kg @endif
                    @if ($template->description) · {{ $template->description }} @endif
                </p>
            </div>
            <button type="button" class="icon-btn" x-show="!renaming" @click="renaming = true" aria-label="Umbenennen"><x-icon name="pencil" size="18" /></button>

            <form method="POST" action="{{ route('templates.update', $template) }}" x-show="renaming" x-cloak class="card flex-1 space-y-3 p-4">
                @csrf @method('PUT')
                <div>
                    <label for="name" class="label">Name</label>
                    <input id="name" name="name" value="{{ old('name', $template->name) }}" required maxlength="80" class="input">
                    @error('name')<p class="field-error">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="description" class="label">Beschreibung</label>
                    <input id="description" name="description" value="{{ old('description', $template->description) }}" maxlength="200" class="input">
                </div>
                <div class="flex gap-2">
                    <button type="submit" class="btn btn-primary">Speichern</button>
                    <button type="button" class="btn btn-ghost" @click="renaming = false">Abbrechen</button>
                </div>
            </form>
        </div>

        @php($empty = $sections->where('hidden', true))
        <div class="space-y-4" x-data="{ shown: [] }">
            @if ($empty->count() === $sections->count())
                <div class="card px-6 py-10 text-center">
                    <p class="text-lg font-extrabold">Noch leer</p>
                    <p class="mt-1 text-[15px] text-muted">Öffne unten eine Kategorie und tipp los, oder füge Gegenstände aus deinem Katalog hinzu.</p>
                </div>
            @endif
            @foreach ($sections as ['group' => $cat, 'items' => $items, 'hidden' => $hidden])
                <section class="card overflow-hidden" x-data="quickAdd(@js(route('templates.items.store', $template)), @js($cat->key), { name: 'new_name', category: 'new_category' })"
                         @if ($hidden) x-cloak x-show="shown.includes(category)" @endif
                         x-on:template-reveal.window="$event.detail === category && $nextTick(() => { $el.scrollIntoView({ block: 'center', behavior: 'smooth' }); $refs.input.focus({ preventScroll: true }) })">
                    <h2 class="card-title flex items-center gap-2 px-5 pt-4 pb-2"><x-icon :name="$cat->icon" size="18" class="text-accent" /> {{ $cat->label }}</h2>
                    <ul class="divide-y divide-line-soft border-b border-line-soft">
                        @foreach ($items as $item)
                            @php($qty = $item->pivot->quantity)
                            <li class="flex min-h-[56px] items-center gap-2 py-1.5 pr-2 pl-5">
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-[15px] font-semibold">{{ $item->name }}</p>
                                    @if ($item->per_day || $item->pack_last)
                                        <p class="text-xs font-bold text-muted">
                                            @if ($item->per_day)<span class="text-accent-ink">pro Tag</span>@endif
                                            @if ($item->per_day && $item->pack_last) · @endif
                                            @if ($item->pack_last)Am Abfahrtstag @endif
                                        </p>
                                    @endif
                                </div>

                                @if ($item->per_day)
                                    <span class="chip bg-accent-soft text-accent-ink" title="Menge ergibt sich aus der Reisedauer">je Tag</span>
                                @else
                                    <div class="flex items-center rounded-chip bg-line-soft">
                                        <form method="POST" action="{{ route('templates.items.update', [$template, $item]) }}" data-keep-scroll>
                                            @csrf @method('PATCH')
                                            <input type="hidden" name="quantity" value="{{ $qty - 1 }}">
                                            <button type="submit" class="inline-flex size-10 items-center justify-center rounded-chip hover:bg-line disabled:opacity-30" @disabled($qty <= 1) aria-label="Weniger {{ $item->name }}"><x-icon name="minus" size="16" /></button>
                                        </form>
                                        <span class="w-8 text-center text-[15px] font-extrabold" aria-label="Menge">{{ $qty }}×</span>
                                        <form method="POST" action="{{ route('templates.items.update', [$template, $item]) }}" data-keep-scroll>
                                            @csrf @method('PATCH')
                                            <input type="hidden" name="quantity" value="{{ $qty + 1 }}">
                                            <button type="submit" class="inline-flex size-10 items-center justify-center rounded-chip hover:bg-line disabled:opacity-30" @disabled($qty >= TemplateItemController::MAX_QUANTITY) aria-label="Mehr {{ $item->name }}"><x-icon name="plus" size="16" /></button>
                                        </form>
                                    </div>
                                @endif

                                <form method="POST" action="{{ route('templates.items.destroy', [$template, $item]) }}" data-keep-scroll>
                                    @csrf @method('DELETE')
                                    <button type="submit" class="icon-btn text-muted hover:text-warn-ink" aria-label="{{ $item->name }} entfernen"><x-icon name="x" size="18" /></button>
                                </form>
                            </li>
                        @endforeach
                        <template x-for="item in added" :key="item.id">
                            <li class="flex min-h-[56px] items-center gap-2 py-1.5 pr-5 pl-5">
                                <p class="min-w-0 flex-1 truncate text-[15px] font-semibold" x-text="item.name"></p>
                                <span class="text-xs font-bold text-accent-ink" x-text="item.badges.length ? item.badges.join(' · ') : 'neu'"></span>
                                <span class="w-8 text-center text-[15px] font-extrabold">1×</span>
                            </li>
                        </template>
                    </ul>
                    <x-quick-add :label="$cat->label" />
                </section>
            @endforeach

            @if ($empty->isNotEmpty())
                <div x-show="shown.length < {{ $empty->count() }}">
                    <p class="mb-2 text-xs font-bold tracking-[0.06em] text-muted uppercase">Weitere Kategorie füllen</p>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($empty as ['group' => $cat])
                            <button type="button" class="chip border border-dashed border-muted/60 text-muted hover:border-ink hover:text-ink"
                                    x-show="!shown.includes(@js($cat->key))" @click="shown.push(@js($cat->key)); $dispatch('template-reveal', @js($cat->key))">
                                <x-icon :name="$cat->icon" size="14" /> {{ $cat->label }}
                            </button>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Hinzufügen --}}
            <section class="card p-5" id="hinzufuegen" x-data="{ q: '', selected: [] }">
                <h2 class="card-title">Gegenstände hinzufügen</h2>

                @if ($available->isNotEmpty())
                    <form method="POST" action="{{ route('templates.items.store', $template) }}" data-keep-scroll class="mt-3">
                        @csrf
                        <div class="relative">
                            <x-icon name="search" size="18" class="pointer-events-none absolute top-1/2 left-3.5 -translate-y-1/2 text-muted" />
                            <input type="search" x-model="q" placeholder="Aus dem Katalog suchen" aria-label="Katalog durchsuchen" class="input pl-10">
                        </div>
                        <ul class="mt-2 max-h-72 overflow-y-auto">
                            @foreach ($available as $item)
                                <li x-show="!q || @js(Str::lower($item->name)).includes(q.toLowerCase())">
                                    <label class="flex min-h-[48px] cursor-pointer items-center gap-3 rounded-chip px-2 hover:bg-line-soft">
                                        <input type="checkbox" name="item_ids[]" value="{{ $item->id }}" x-model="selected" class="size-5 accent-[var(--accent)]">
                                        <x-icon :name="$item->categoryGroup()->icon" size="16" class="text-muted" />
                                        <span class="text-[15px] font-semibold">{{ $item->name }}</span>
                                    </label>
                                </li>
                            @endforeach
                        </ul>
                        @error('item_ids')<p class="field-error">{{ $message }}</p>@enderror
                        <button type="submit" class="btn btn-primary mt-3" :disabled="selected.length === 0">
                            <x-icon name="plus" size="18" /> <span x-text="selected.length > 1 ? selected.length + ' hinzufügen' : 'Hinzufügen'">Hinzufügen</span>
                        </button>
                    </form>
                @endif

                <form method="POST" action="{{ route('templates.items.store', $template) }}" data-keep-scroll class="{{ $available->isNotEmpty() ? 'mt-5 border-t border-line-soft pt-4' : 'mt-3' }}">
                    @csrf
                    <p class="label">Neu anlegen</p>
                    <div class="flex flex-col gap-2 sm:flex-row">
                        <input name="new_name" value="{{ old('new_name') }}" required maxlength="120" placeholder="Name" aria-label="Name des neuen Gegenstands" class="input flex-1">
                        <select name="new_category" required aria-label="Kategorie" class="input sm:w-56">
                            @foreach ($categories as $choice)
                                <option value="{{ $choice->key }}" @selected(old('new_category', 'misc') === $choice->key)>{{ $choice->label }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="btn btn-secondary"><x-icon name="plus" size="18" /> Anlegen</button>
                    </div>
                    @error('new_name')<p class="field-error">{{ $message }}</p>@enderror
                    <p class="mt-2 text-xs font-semibold text-muted">Landet auch im Katalog. Gewicht und „pro Tag“ stellst du dort ein.</p>
                </form>
            </section>
        </div>

        <div class="card mt-10 p-5" x-data="{ confirming: false }">
            <p class="card-title">Vorlage löschen</p>
            <p class="mt-1 text-sm text-muted">Die Gegenstände bleiben im Katalog.</p>
            <button type="button" class="btn btn-danger mt-4" x-show="!confirming" @click="confirming = true"><x-icon name="trash" size="18" /> Vorlage löschen</button>
            <form method="POST" action="{{ route('templates.destroy', $template) }}" x-show="confirming" x-cloak class="mt-4 flex flex-wrap gap-2">
                @csrf @method('DELETE')
                <button type="submit" class="btn bg-warn text-white hover:bg-warn-ink">Ja, löschen</button>
                <button type="button" class="btn btn-secondary" @click="confirming = false">Abbrechen</button>
            </form>
        </div>
    </div>
</x-layouts.app>
