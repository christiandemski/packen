@php
    use App\Enums\PacklistGroup;
    use App\Enums\EntrySource;
    use Illuminate\Support\Str;

    $packedState = $groups->flatten()->mapWithKeys(fn ($e) => [$e->id => $e->packed]);
    $isEmpty = $groups->isEmpty();
    $selectedTemplates = $templates->whereIn('id', $selected);
@endphp
<x-layouts.app :title="$trip->name">
    <div class="mx-auto max-w-2xl" x-data="{ tab: 'packlist' }">
        <x-trip-cover :trip="$trip" :icon-size="120" class="-mx-4 -mt-6 mb-5 h-36 sm:mx-0 sm:mt-0 sm:rounded-card">
            <div class="absolute inset-0 bg-gradient-to-t from-black/50 to-black/10"></div>
            <div class="absolute inset-x-3 top-3 flex items-center justify-between">
                <a href="{{ route('home') }}" class="icon-btn bg-white/95 text-ink hover:bg-white" aria-label="Zurück zur Übersicht"><x-icon name="arrow-left" /></a>
                @can('update', $trip)
                    <a href="{{ route('trips.edit', $trip) }}" class="btn bg-white/95 text-ink hover:bg-white"><x-icon name="pencil" size="16" /> Bearbeiten</a>
                @endcan
            </div>
            <span class="chip absolute bottom-3 left-4 bg-white/95 text-ink"><x-icon :name="$trip->mode->icon()" size="14" /> {{ $trip->mode->label() }}</span>
        </x-trip-cover>

        <h1 class="page-title">{{ $trip->name }}</h1>
        <p class="mt-1.5 text-[15px] font-semibold text-muted">
            {{ $trip->dateRangeLabel() }} · {{ $trip->days() }} {{ $trip->days() === 1 ? 'Tag' : 'Tage' }} · {{ $trip->type->label() }}
        </p>

        <div class="segment mt-5" role="tablist" aria-label="Bereiche der Reise">
            @foreach (['packlist' => ['list-checks', 'Packliste'], 'outfits' => ['shirt', 'Outfits'], 'review' => ['history', 'Rückblick']] as $key => [$icon, $label])
                <button type="button" role="tab" class="segment-item" :aria-selected="tab === '{{ $key }}'" @click="tab = '{{ $key }}'">
                    <x-icon :name="$icon" size="16" /> {{ $label }}
                </button>
            @endforeach
        </div>

        {{-- ───────────── Packliste ───────────── --}}
        <div role="tabpanel" x-show="tab === 'packlist'" class="mt-4" x-data="packlist(@js((object) $packedState->all()))">
            @if ($isEmpty)
                <div class="card p-5">
                    <p class="card-title">Stell deine Packliste zusammen</p>
                    <p class="mt-1 text-sm text-muted">Wähle Module – doppelte Gegenstände werden zusammengeführt, „pro Tag“ wird mit {{ $trip->days() }} Tagen gerechnet.</p>

                    @if ($templates->isNotEmpty())
                        <form method="POST" action="{{ route('packlist.templates', $trip) }}" class="mt-4">
                            @csrf @method('PUT')
                            <div class="grid gap-2 sm:grid-cols-2">
                                @foreach ($templates as $template)
                                    <label class="flex min-h-[52px] cursor-pointer items-center gap-3 rounded-chip border border-line px-3 py-2 has-checked:border-accent has-checked:bg-accent-soft/50">
                                        <input type="checkbox" name="template_ids[]" value="{{ $template->id }}" @checked($suggested->contains($template->id)) class="size-5 accent-[var(--accent)]">
                                        <span><span class="block text-[15px] font-bold">{{ $template->name }}</span><span class="block text-xs font-semibold text-muted">{{ $template->items_count }} Gegenstände</span></span>
                                    </label>
                                @endforeach
                            </div>
                            <button type="submit" class="btn btn-primary mt-4 w-full sm:w-auto"><x-icon name="layers" size="18" /> Packliste erstellen</button>
                        </form>
                    @else
                        <p class="mt-4 rounded-chip bg-line-soft p-3 text-sm font-semibold text-muted">
                            Du hast noch keine Vorlagen. <a href="{{ route('templates.index') }}" class="text-accent-ink underline">Vorlagen anlegen oder Start-Vorlagen übernehmen</a> – oder füge unten einfach einzelne Gegenstände hinzu.
                        </p>
                    @endif

                    @if ($otherTrips->isNotEmpty())
                        <form method="POST" action="{{ route('packlist.copy', $trip) }}" class="mt-5 border-t border-line-soft pt-4">
                            @csrf
                            <label for="source_trip_id" class="label">Oder von einer früheren Reise übernehmen</label>
                            <div class="flex flex-col gap-2 sm:flex-row">
                                <select id="source_trip_id" name="source_trip_id" class="input flex-1">
                                    @foreach ($otherTrips as $other)
                                        <option value="{{ $other->id }}">{{ $other->name }} · {{ $other->dateRangeLabel() }}</option>
                                    @endforeach
                                </select>
                                <button type="submit" class="btn btn-secondary"><x-icon name="repeat" size="18" /> Übernehmen</button>
                            </div>
                        </form>
                    @endif
                </div>
            @else
                {{-- Fortschritt --}}
                <div class="card p-5">
                    <div class="flex items-baseline justify-between gap-3">
                        <p class="text-[15px] font-bold"><span x-text="done">{{ $progress['packed'] }}</span> von <span x-text="total">{{ $progress['total'] }}</span> gepackt</p>
                        <p class="text-2xl font-extrabold text-accent"><span x-text="percent">{{ $progress['total'] ? round($progress['packed'] / $progress['total'] * 100) : 0 }}</span> %</p>
                    </div>
                    <div class="mt-3 h-2.5 overflow-hidden rounded-full bg-line-soft" role="progressbar" aria-label="Packfortschritt" :aria-valuenow="percent" aria-valuemin="0" aria-valuemax="100">
                        <div class="h-full rounded-full bg-accent transition-[width] duration-300" :style="`width: ${percent}%`" style="width: {{ $progress['total'] ? round($progress['packed'] / $progress['total'] * 100) : 0 }}%"></div>
                    </div>

                    <div class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-line-soft pt-4">
                        <div class="flex min-w-0 flex-wrap items-center gap-1.5">
                            <span class="mr-1 text-xs font-bold text-muted">Zusammengesetzt aus</span>
                            @foreach ($selectedTemplates as $template)
                                <span class="chip bg-ink text-white">{{ $template->name }}</span>
                            @endforeach
                            <button type="button" class="chip border border-dashed border-muted/60 text-muted hover:border-ink hover:text-ink" @click="$dispatch('open-sheet', 'modules')">
                                <x-icon name="plus" size="12" /> Modul
                            </button>
                        </div>
                        <button type="button" role="switch" :aria-checked="onlyOpen" @click="toggleOnlyOpen" class="flex min-h-11 items-center gap-2 text-sm font-bold">
                            Nur offene
                            <span class="relative inline-flex h-6 w-10 rounded-full transition" :class="onlyOpen ? 'bg-accent' : 'bg-line'">
                                <span class="absolute top-0.5 left-0.5 size-5 rounded-full bg-white shadow transition" :class="onlyOpen && 'translate-x-4'"></span>
                            </span>
                        </button>
                    </div>
                </div>

                <p x-show="error" x-text="error" x-cloak role="alert" class="mt-3 rounded-chip bg-warn-soft px-4 py-3 text-sm font-bold text-warn-ink"></p>

                <div class="mt-4 space-y-4">
                    @foreach ($groups as $groupKey => $entries)
                        @php($group = PacklistGroup::from($groupKey))
                        @php($ids = $entries->pluck('id')->values())
                        <section class="card overflow-hidden" x-show="!onlyOpen || openIn(@js($ids)) > 0">
                            <div class="flex items-center justify-between gap-3 px-5 pt-4 pb-2">
                                <h2 class="card-title flex items-center gap-2">
                                    <x-icon :name="$group->icon()" size="18" class="text-accent" /> {{ $group->label() }}
                                    @if ($group === PacklistGroup::DepartureDay)
                                        <span class="chip bg-accent-soft text-accent-ink">Zuletzt</span>
                                    @endif
                                </h2>
                                <span class="text-xs font-bold text-muted"><span x-text="{{ $ids->count() }} - openIn(@js($ids))"></span>/{{ $ids->count() }}</span>
                            </div>
                            <ul class="divide-y divide-line-soft">
                                @foreach ($entries as $entry)
                                    @php($toggleUrl = route('packlist.toggle', [$trip, $entry]))
                                    <li x-data="{ edit: false }" x-show="!onlyOpen || !packed[{{ $entry->id }}]">
                                        <div class="flex min-h-[52px] items-center">
                                            <button type="button" role="checkbox" :aria-checked="!!packed[{{ $entry->id }}]" aria-checked="{{ $entry->packed ? 'true' : 'false' }}"
                                                    @click="toggle({{ $entry->id }}, @js($toggleUrl))"
                                                    class="flex min-w-0 flex-1 items-center gap-3 py-2.5 pl-5 text-left">
                                                <span class="inline-flex size-6 shrink-0 items-center justify-center rounded-[7px] border-2 transition"
                                                      :class="packed[{{ $entry->id }}] ? 'border-accent bg-accent text-white' : 'border-line bg-surface text-transparent'">
                                                    <x-icon name="check" size="16" :stroke-width="3" />
                                                </span>
                                                <span class="min-w-0">
                                                    <span class="block truncate text-[15px] font-semibold transition" :class="packed[{{ $entry->id }}] && 'text-muted line-through'">{{ $entry->name() }}</span>
                                                    @if ($entry->note || $entry->item?->per_day)
                                                        <span class="block truncate text-xs font-semibold text-muted">
                                                            {{ $entry->note }}
                                                            @if ($entry->item?->per_day && ! $entry->quantity_locked && $entry->source === EntrySource::Template){{ $entry->note ? ' · ' : '' }}{{ $trip->days() }} Tage @endif
                                                        </span>
                                                    @endif
                                                </span>
                                            </button>
                                            <button type="button" @click="edit = !edit" :aria-expanded="edit" class="mr-3 ml-2 inline-flex min-h-11 min-w-11 items-center justify-center rounded-chip px-2" aria-label="{{ $entry->name() }} bearbeiten">
                                                <span class="rounded-full bg-line-soft px-2.5 py-1 text-[13px] font-extrabold {{ $entry->quantity_locked ? 'text-accent-ink' : 'text-ink' }}">{{ $entry->quantity }}×</span>
                                            </button>
                                        </div>
                                        <div x-show="edit" x-cloak class="flex flex-wrap items-end gap-2 bg-line-soft/60 px-5 py-3">
                                            <form method="POST" action="{{ route('packlist.update', [$trip, $entry]) }}" data-keep-scroll class="flex flex-1 flex-wrap items-end gap-2">
                                                @csrf @method('PATCH')
                                                <div class="w-24">
                                                    <label class="label" for="qty-{{ $entry->id }}">Menge</label>
                                                    <input id="qty-{{ $entry->id }}" name="quantity" type="number" min="1" max="999" inputmode="numeric" value="{{ $entry->quantity }}" class="input">
                                                </div>
                                                <div class="min-w-40 flex-1">
                                                    <label class="label" for="note-{{ $entry->id }}">Notiz</label>
                                                    <input id="note-{{ $entry->id }}" name="note" maxlength="200" value="{{ $entry->note }}" class="input">
                                                </div>
                                                <button type="submit" class="btn btn-primary">Speichern</button>
                                            </form>
                                            <form method="POST" action="{{ route('packlist.destroy', [$trip, $entry]) }}" data-keep-scroll>
                                                @csrf @method('DELETE')
                                                <button type="submit" class="icon-btn text-warn-ink" aria-label="{{ $entry->name() }} entfernen"><x-icon name="trash" size="18" /></button>
                                            </form>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        </section>
                    @endforeach

                    <p x-show="onlyOpen && done === total" x-cloak class="card p-6 text-center text-[15px] font-bold text-accent-ink">
                        Alles gepackt – gute Reise!
                    </p>
                </div>
            @endif

            <button type="button" @click="$dispatch('open-sheet', 'add-entry')"
                    class="btn btn-primary fixed right-4 bottom-20 z-20 shadow-[0_12px_28px_-10px_rgba(14,107,94,0.6)] sm:bottom-6">
                <x-icon name="plus" size="18" /> Gegenstand
            </button>
        </div>

        {{-- ───────────── Outfits / Rückblick (folgen) ───────────── --}}
        <div class="card mt-4 p-5" role="tabpanel" x-show="tab === 'outfits'" x-cloak>
            <p class="card-title">Outfits</p>
            <p class="mt-1 text-sm text-muted">Der Outfit-Planer mit Wochenübersicht kommt mit Meilenstein 4.</p>
        </div>
        <div class="card mt-4 p-5" role="tabpanel" x-show="tab === 'review'" x-cloak>
            <p class="card-title">Rückblick</p>
            <p class="mt-1 text-sm text-muted">Nach der Reise markierst du hier, was gefehlt hat oder unnötig war (Meilenstein 6).</p>
        </div>

        {{-- ───────────── Reisedetails ───────────── --}}
        <details class="card mt-8 overflow-hidden">
            <summary class="flex min-h-[52px] cursor-pointer items-center justify-between px-5 text-[15px] font-bold">
                Reisedetails{{ $trip->isGroup() ? ' & Mitreisende' : '' }}
                <x-icon name="chevron-right" size="18" class="text-muted" />
            </summary>
            <div class="divide-y divide-line-soft border-t border-line-soft">
                @foreach (array_filter([
                    ['map-pin', 'Reiseziel', $trip->location],
                    ['calendar', 'Zeitraum', $trip->dateRangeLabel().' ('.$trip->countdownLabel().')'],
                    [$trip->transport?->icon() ?? 'compass', 'Anreise mit', $trip->transport?->label()],
                    [$trip->accommodation?->icon() ?? 'bed', 'Unterkunft', $trip->accommodation?->label()],
                ], fn ($row) => filled($row[2])) as [$icon, $label, $value])
                    <div class="flex min-h-[52px] items-center gap-3 px-5 py-3">
                        <x-icon :name="$icon" size="18" class="text-muted" />
                        <span class="w-28 shrink-0 text-xs font-bold tracking-[0.06em] text-muted uppercase">{{ $label }}</span>
                        <span class="text-[15px] font-semibold">{{ $value }}</span>
                    </div>
                @endforeach
                @if ($trip->isGroup())
                    <ul class="px-5 py-2">
                        @foreach ($trip->members->sortBy(fn ($m) => $m->pivot->role->value === 'owner' ? 0 : 1) as $member)
                            <li class="flex min-h-[52px] items-center gap-3">
                                <x-avatar :user="$member" />
                                <span class="text-[15px] font-semibold">{{ $member->name }}@if ($member->is(auth()->user())) <span class="text-muted">(du)</span>@endif</span>
                                @if ($member->pivot->role->value === 'owner')
                                    <span class="chip ml-auto bg-accent-soft text-accent-ink">Organisiert</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </details>
    </div>

    {{-- Module wählen --}}
    <x-sheet name="modules" title="Module">
        @if ($templates->isNotEmpty())
            <form method="POST" action="{{ route('packlist.templates', $trip) }}">
                @csrf @method('PUT')
                <p class="mb-3 text-sm text-muted">Abgewählte Module nehmen ihre Gegenstände wieder mit – außer du hast sie selbst hinzugefügt.</p>
                <div class="space-y-2">
                    @foreach ($templates as $template)
                        <label class="flex min-h-[52px] cursor-pointer items-center gap-3 rounded-chip border border-line px-3 py-2 has-checked:border-accent has-checked:bg-accent-soft/50">
                            <input type="checkbox" name="template_ids[]" value="{{ $template->id }}" @checked($selected->contains($template->id)) class="size-5 accent-[var(--accent)]">
                            <span class="flex-1 text-[15px] font-bold">{{ $template->name }}</span>
                            <span class="text-xs font-semibold text-muted">{{ $template->items_count }}</span>
                        </label>
                    @endforeach
                </div>
                <button type="submit" class="btn btn-primary mt-4 w-full">Übernehmen</button>
            </form>
        @else
            <p class="text-sm text-muted">Noch keine Vorlagen. <a href="{{ route('templates.index') }}" class="font-bold text-accent-ink underline">Zu den Vorlagen</a></p>
        @endif
        @if ($otherTrips->isNotEmpty() && ! $isEmpty)
            <form method="POST" action="{{ route('packlist.copy', $trip) }}" class="mt-5 border-t border-line-soft pt-4">
                @csrf
                <label for="sheet_source_trip" class="label">Von früherer Reise ergänzen</label>
                <div class="flex flex-col gap-2 sm:flex-row">
                    <select id="sheet_source_trip" name="source_trip_id" class="input flex-1">
                        @foreach ($otherTrips as $other)
                            <option value="{{ $other->id }}">{{ $other->name }} · {{ $other->dateRangeLabel() }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="btn btn-secondary">Ergänzen</button>
                </div>
            </form>
        @endif
    </x-sheet>

    {{-- Gegenstand hinzufügen --}}
    <x-sheet name="add-entry" title="Gegenstand hinzufügen">
        <div x-data="{ q: '' }">
            @if ($addable->isNotEmpty())
                <form method="POST" action="{{ route('packlist.store', $trip) }}" x-ref="catalogForm" data-keep-scroll>
                    @csrf
                    <input type="hidden" name="item_id" x-ref="itemId">
                </form>
                <div class="relative">
                    <x-icon name="search" size="18" class="pointer-events-none absolute top-1/2 left-3.5 -translate-y-1/2 text-muted" />
                    <input type="search" x-model="q" autofocus placeholder="Im Katalog suchen" aria-label="Im Katalog suchen" class="input pl-10">
                </div>
                <ul class="mt-2 max-h-64 overflow-y-auto">
                    @foreach ($addable as $item)
                        <li x-show="!q || @js(Str::lower($item->name)).includes(q.toLowerCase())">
                            <button type="button" @click="$refs.itemId.value = {{ $item->id }}; $refs.catalogForm.submit()"
                                    class="flex min-h-[48px] w-full items-center gap-3 rounded-chip px-2 text-left hover:bg-line-soft">
                                <x-icon :name="$item->category->icon()" size="16" class="text-muted" />
                                <span class="flex-1 text-[15px] font-semibold">{{ $item->name }}</span>
                                <x-icon name="plus" size="16" class="text-accent" />
                            </button>
                        </li>
                    @endforeach
                </ul>
            @endif

            <form method="POST" action="{{ route('packlist.store', $trip) }}" data-keep-scroll class="{{ $addable->isNotEmpty() ? 'mt-4 border-t border-line-soft pt-4' : '' }}">
                @csrf
                <p class="label">Freitext (nur für diese Reise)</p>
                <input name="custom_name" required maxlength="120" :value="q" placeholder="z. B. Geschenk für Oma" aria-label="Name" class="input">
                <div class="mt-2 flex gap-2">
                    <select name="group" aria-label="Gruppe" class="input flex-1">
                        @foreach (PacklistGroup::cases() as $case)
                            <option value="{{ $case->value }}" @selected($case === PacklistGroup::Misc)>{{ $case->label() }}</option>
                        @endforeach
                    </select>
                    <input name="quantity" type="number" min="1" max="999" value="1" aria-label="Menge" class="input w-20">
                </div>
                @error('custom_name')<p class="field-error">{{ $message }}</p>@enderror
                <button type="submit" class="btn btn-secondary mt-3 w-full"><x-icon name="plus" size="18" /> Hinzufügen</button>
            </form>
        </div>
    </x-sheet>
</x-layouts.app>
