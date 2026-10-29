@php
    use App\Enums\PacklistGroup;
    use App\Models\Container;
    use Illuminate\Support\Str;

    $packedState = $groups->flatten()->mapWithKeys(fn ($e) => [$e->id => $e->packed]);
    // Rückweg: alles außer Aufgaben („Herd aus“ muss nicht zurück)
    $returnState = $groups->flatten()->reject(fn ($e) => $e->isTask())->mapWithKeys(fn ($e) => [$e->id => $e->returned]);
    $isEmpty = $groups->isEmpty();
    $selectedTemplates = $templates->whereIn('id', $selected);
@endphp
<x-layouts.app :title="$trip->name">
    <div class="mx-auto max-w-2xl" x-data="{ tab: @js($initialTab) }">
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
            {{ $trip->dateRangeLabel() }} · {{ $trip->durationLabel() }} · {{ $trip->type->label() }}
        </p>

        <div class="segment mt-5" role="tablist" aria-label="Bereiche der Reise">
            @foreach (['packlist' => ['list-checks', 'Packliste'], 'outfits' => ['shirt', 'Outfits'], 'review' => ['history', 'Rückblick']] as $key => [$icon, $label])
                <button type="button" role="tab" class="segment-item" :aria-selected="tab === '{{ $key }}'" @click="tab = '{{ $key }}'">
                    <x-icon :name="$icon" size="16" /> {{ $label }}
                </button>
            @endforeach
        </div>

        {{-- ───────────── Packliste ───────────── --}}
        <div role="tabpanel" x-show="tab === 'packlist'" class="mt-4" x-data="packlist(@js((object) $packedState->all()), @js($initialView), @js((object) $returnState->all()), @js($initialDirection))">
            @if ($grantedToMe->isNotEmpty())
                <div class="segment mb-4" role="group" aria-label="Wessen Packliste">
                    <a href="{{ route('trips.show', $trip) }}" class="segment-item" aria-pressed="{{ $canEdit ? 'true' : 'false' }}">Meine Liste</a>
                    @foreach ($grantedToMe as $owner)
                        <a href="{{ route('trips.show', ['trip' => $trip, 'liste' => $owner->id]) }}" class="segment-item" aria-pressed="{{ $listOwner->is($owner) ? 'true' : 'false' }}">{{ $owner->name }}</a>
                    @endforeach
                </div>
            @endif
            @unless ($canEdit)
                <p class="mb-4 flex items-center gap-2 rounded-chip bg-accent-soft px-4 py-3 text-sm font-bold text-accent-ink">
                    <x-icon name="users" size="16" /> Du packst für {{ $listOwner->name }} mit – abhaken geht, ändern nur {{ $listOwner->name }}.
                </p>
            @endunless
            @include('trips._group-requests')
            @include('trips._weather-hints')
            @if ($isEmpty && $canEdit)
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
            @elseif ($isEmpty)
                <div class="card p-5 text-sm text-muted">Diese Liste ist noch leer.</div>
            @else
                @if ($returnAvailable)
                    <div class="segment mb-4" role="group" aria-label="Richtung">
                        <button type="button" class="segment-item" :aria-pressed="direction === 'there'" @click="direction = 'there'"><x-icon name="luggage" size="16" /> Hinweg</button>
                        <button type="button" class="segment-item" :aria-pressed="direction === 'back'" @click="direction = 'back'; editing = false"><x-icon name="repeat" size="16" /> Rückweg</button>
                    </div>
                    <p x-show="direction === 'back'" x-cloak class="mb-4 flex items-start gap-2 rounded-chip bg-accent-soft px-4 py-3 text-sm font-semibold text-accent-ink">
                        <x-icon name="repeat" size="16" class="mt-0.5" />
                        <span>Rückweg: Hak ab, was wieder im Gepäck ist – Ladekabel in der Steckdose, Jacke an der Garderobe?</span>
                    </p>
                @endif

                {{-- Fortschritt --}}
                <div class="card p-5">
                    <div class="flex items-baseline justify-between gap-3">
                        <p class="text-[15px] font-bold"><span x-text="done">{{ $progress['packed'] }}</span> von <span x-text="total">{{ $progress['total'] }}</span> <span x-text="direction === 'back' ? 'wieder eingepackt' : 'gepackt'">gepackt</span></p>
                        <p class="text-2xl font-extrabold text-accent"><span x-text="percent">{{ $progress['total'] ? round($progress['packed'] / $progress['total'] * 100) : 0 }}</span> %</p>
                    </div>
                    <div class="mt-3 h-2.5 overflow-hidden rounded-full bg-line-soft" role="progressbar" aria-label="Packfortschritt" :aria-valuenow="percent" aria-valuemin="0" aria-valuemax="100">
                        <div class="h-full rounded-full bg-accent transition-[width] duration-300" :style="`width: ${percent}%`" style="width: {{ $progress['total'] ? round($progress['packed'] / $progress['total'] * 100) : 0 }}%"></div>
                    </div>
                    @if ($weight['total'] > 0)
                        <p class="mt-3 flex flex-wrap items-center gap-x-1.5 text-sm font-semibold text-muted">
                            <x-icon name="scale" size="16" />
                            <span>Gesamtgewicht <span class="font-extrabold text-ink">{{ $weight['missing'] ? 'mind. ' : '' }}{{ Container::kg($weight['total']) }}</span></span>
                            @if ($weight['containers'])<span>· davon {{ Container::kg($weight['containers']) }} Behältnisse</span>@endif
                            @if ($weight['worn'])<span title="Das Outfit für die Anreise trägst du am Körper.">· ohne {{ Container::kg($weight['worn']) }} Anreise-Kleidung</span>@endif
                            @if ($weight['missing'])<span>· {{ $weight['missing'] }} {{ $weight['missing'] === 1 ? 'Eintrag' : 'Einträge' }} ohne Gewicht</span>@endif
                        </p>
                    @endif

                    <div class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-line-soft pt-4">
                        <div class="flex min-w-0 flex-wrap items-center gap-1.5 {{ $canEdit ? '' : 'invisible' }}">
                            <span class="mr-1 text-xs font-bold text-muted">Zusammengesetzt aus</span>
                            @foreach ($selectedTemplates as $template)
                                <span class="chip bg-ink text-white">{{ $template->name }}</span>
                            @endforeach
                            <button type="button" class="chip border border-dashed border-muted/60 text-muted hover:border-ink hover:text-ink" @click="$dispatch('open-sheet', 'modules')">
                                <x-icon name="plus" size="12" /> Modul
                            </button>
                        </div>
                        <div class="flex flex-wrap items-center gap-1">
                        @if ($canEdit)
                            <button type="button" x-show="direction === 'there'" class="btn min-h-11 px-3 text-sm" :class="editing ? 'btn-primary' : 'btn-ghost'" @click="editing = !editing" :aria-pressed="editing">
                                <x-icon name="pencil" size="16" /> <span x-text="editing ? 'Fertig' : 'Anpassen'">Anpassen</span>
                            </button>
                        @endif
                        <button type="button" class="btn btn-ghost min-h-11 px-3 text-sm" @click="$dispatch('open-sheet', 'export')"><x-icon name="share" size="16" /> Exportieren</button>
                        <button type="button" role="switch" :aria-checked="onlyOpen" @click="toggleOnlyOpen" class="flex min-h-11 items-center gap-2 text-sm font-bold">
                            Nur offene
                            <span class="relative inline-flex h-6 w-10 rounded-full transition" :class="onlyOpen ? 'bg-accent' : 'bg-line'">
                                <span class="absolute top-0.5 left-0.5 size-5 rounded-full bg-white shadow transition" :class="onlyOpen && 'translate-x-4'"></span>
                            </span>
                        </button>
                        </div>
                    </div>
                </div>


                @if ($canEdit)
                    <div x-show="editing" x-cloak class="mt-4 rounded-chip bg-accent-soft px-4 py-3 text-sm font-semibold text-accent-ink">
                        <p class="flex items-start gap-2"><x-icon name="pencil" size="16" class="mt-0.5" /> <span>Tipp auf <x-icon name="x" size="14" class="inline align-[-2px]" />, was du diesmal nicht brauchst. Deine Vorlagen und der Katalog bleiben, wie sie sind.</span></p>
                        <p x-show="removeError" x-text="removeError" class="mt-2 text-warn-ink" role="alert"></p>
                    </div>
                @endif

                @if ($userContainers->isNotEmpty())
                    <div class="segment mt-4" role="group" aria-label="Ansicht der Packliste">
                        <button type="button" class="segment-item" :aria-pressed="view === 'groups'" @click="view = 'groups'"><x-icon name="list-checks" size="16" /> Nach Gruppe</button>
                        <button type="button" class="segment-item" :aria-pressed="view === 'containers'" @click="view = 'containers'"><x-icon name="luggage" size="16" /> Nach Behältnis</button>
                    </div>
                @endif

                {{-- Nach Gruppe --}}
                <div class="mt-4 space-y-4" x-show="view === 'groups'">
                    @foreach ($groups as $entries)
                        @php($group = $entries->first()->packlistGroup())
                        @php($ids = $entries->pluck('id')->values())
                        <section class="card overflow-hidden" x-show="editing || (present(@js($ids)).length && (!onlyOpen || openIn(@js($ids)) > 0))">
                            <div class="flex items-center justify-between gap-3 px-5 pt-4 pb-2">
                                <h2 class="card-title flex items-center gap-2">
                                    <x-icon :name="$group->icon" size="18" class="text-accent" /> {{ $group->label }}
                                    @if ($group->is(PacklistGroup::DepartureDay))
                                        <span class="chip bg-accent-soft text-accent-ink">Zuletzt</span>
                                    @elseif ($group->is(PacklistGroup::Clothing) && $hasOutfitEntries)
                                        <button type="button" @click="tab = 'outfits'; window.scrollTo({ top: 0, behavior: 'smooth' })" class="chip bg-accent-soft text-accent-ink hover:bg-accent hover:text-white">Aus Outfits</button>
                                    @endif
                                </h2>
                                <span class="text-xs font-bold text-muted" x-show="!editing" x-text="`${present(@js($ids)).length - openIn(@js($ids))}/${present(@js($ids)).length}`">{{ $entries->where('packed', true)->count() }}/{{ $ids->count() }}</span>
                                @if ($canEdit && ! $group->is(PacklistGroup::Shared))
                                    <button type="button" x-show="editing && present(@js($ids)).length" x-cloak
                                            @click="removeAll(@js($entries->map(fn ($e) => ['id' => $e->id, 'url' => route('packlist.destroy', [$trip, $e])])->values()))"
                                            class="chip min-h-9 shrink-0 border border-line text-muted hover:border-warn-ink hover:text-warn-ink">
                                        <x-icon name="x" size="12" /> Ganze Karte weglassen
                                    </button>
                                @endif
                            </div>
                            <ul class="divide-y divide-line-soft">
                                @foreach ($entries as $entry)
                                    <x-packlist-row :trip="$trip" :entry="$entry" :containers="$tripContainers" :can-edit="$canEdit" view="groups" />
                                @endforeach
                            </ul>
                        </section>
                    @endforeach
                </div>

                {{-- Für diese Reise weggelassen --}}
                @if ($excluded->isNotEmpty())
                    <section class="card mt-4 overflow-hidden" x-data="{ open: @js($excludedHints !== []) }">
                        <button type="button" @click="open = !open" :aria-expanded="open" class="flex min-h-[56px] w-full items-center gap-3 px-5 py-3 text-left">
                            <x-icon name="ban" size="18" class="text-muted" />
                            <span class="flex-1">
                                <span class="block text-[15px] font-bold">Diesmal nicht dabei</span>
                                <span class="block text-xs font-semibold text-muted">{{ $excluded->count() }} {{ $excluded->count() === 1 ? 'Eintrag' : 'Einträge' }} aus deinen Vorlagen weggelassen</span>
                                @if ($excludedHints)
                                    <span class="block text-xs font-bold text-accent-ink">{{ count($excludedHints) === 1 ? 'Einer davon fehlt' : count($excludedHints).' davon fehlen' }} schon öfter – Vorlage aufräumen?</span>
                                @endif
                            </span>
                            <x-icon name="chevron-right" size="16" class="text-muted transition" ::class="open && 'rotate-90'" />
                        </button>
                        <ul x-show="open" x-cloak class="divide-y divide-line-soft border-t border-line-soft">
                            @foreach ($excluded as $entry)
                                @php($hint = $excludedHints[$entry->id] ?? null)
                                <li class="py-1.5 pr-3 pl-5">
                                    <div class="flex min-h-[44px] items-center gap-3">
                                        <span class="min-w-0 flex-1 truncate text-[15px] font-semibold text-muted">{{ $entry->name() }}</span>
                                        <form method="POST" action="{{ route('packlist.restore', [$trip, $entry]) }}" data-keep-scroll>
                                            @csrf
                                            <button type="submit" class="min-h-11 px-2 text-sm font-bold text-accent-ink hover:underline">Doch mitnehmen</button>
                                        </form>
                                    </div>
                                    @if ($hint)
                                        <div class="mb-2 flex flex-wrap items-center gap-2 rounded-chip bg-accent-soft px-3 py-2 text-xs font-semibold text-accent-ink">
                                            <span class="flex-1 basis-40">Schon auf {{ $hint['times'] }} Reisen weggelassen.</span>
                                            @foreach ($hint['templates'] as $template)
                                                <form method="POST" action="{{ route('review.template.remove', [$trip, $entry, $template]) }}" data-keep-scroll>
                                                    @csrf @method('DELETE')
                                                    <input type="hidden" name="zurueck" value="packliste">
                                                    <button type="submit" class="chip min-h-9 bg-surface text-accent-ink hover:bg-accent hover:text-white">Aus „{{ $template->name }}“ streichen</button>
                                                </form>
                                            @endforeach
                                        </div>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endif

                {{-- Nach Behältnis --}}
                @if ($userContainers->isNotEmpty())
                    <div class="mt-4 space-y-4" x-show="view === 'containers'" x-cloak>
                        <div class="card flex flex-wrap items-center gap-3 p-5">
                            <div class="min-w-0 flex-1">
                                @if ($tripContainers->isEmpty())
                                    <p class="card-title">Worin packst du?</p>
                                    <p class="mt-1 text-sm text-muted">Wähle die Behältnisse für diese Reise – Gegenstände mit Standardplatz landen automatisch darin.</p>
                                @else
                                    <p class="text-xs font-bold tracking-[0.06em] text-muted uppercase">Du brauchst</p>
                                    <p class="mt-0.5 text-[17px] font-extrabold">{{ $neededLabel ?? 'noch nichts zugeordnet' }}</p>
                                    @if ($unassigned->isNotEmpty())
                                        <p class="mt-0.5 text-sm font-semibold text-muted">{{ $unassigned->count() }} {{ $unassigned->count() === 1 ? 'Eintrag' : 'Einträge' }} noch ohne Behältnis</p>
                                    @endif
                                @endif
                            </div>
                            <button type="button" class="btn btn-secondary" @click="$dispatch('open-sheet', 'containers')"><x-icon name="luggage" size="18" /> Behältnisse wählen</button>
                        </div>

                        @foreach ($containerSummary as $row)
                            @php($ids = $row['entries']->pluck('id')->values())
                            <section class="card overflow-hidden" x-show="!onlyOpen || {{ $ids->isEmpty() ? 'true' : 'false' }} || openIn(@js($ids)) > 0">
                                <div class="flex items-start justify-between gap-3 px-5 pt-4 pb-2">
                                    <div>
                                        <h2 class="card-title flex items-center gap-2"><x-icon :name="$row['container']->kind->icon()" size="18" class="text-accent" /> {{ $row['container']->name }}</h2>
                                        <p class="mt-0.5 text-xs font-bold {{ $row['over'] ? 'text-warn-ink' : 'text-muted' }}">
                                            {{ $row['entries']->count() }} {{ $row['entries']->count() === 1 ? 'Eintrag' : 'Einträge' }}@if ($row['weight'] > 0) · {{ Container::kg($row['weight']) }}@endif @if ($row['container']->max_weight_g) von max. {{ Container::kg($row['container']->max_weight_g) }}@endif @if ($row['over']) – zu schwer!@endif
                                        </p>
                                    </div>
                                    @if ($ids->isNotEmpty())
                                        <span class="text-xs font-bold text-muted" x-text="`${present(@js($ids)).length - openIn(@js($ids))}/${present(@js($ids)).length}`">{{ $row['entries']->where('packed', true)->count() }}/{{ $ids->count() }}</span>
                                    @endif
                                </div>
                                @if ($row['container']->max_weight_g)
                                    @php($load = min(100, (int) round($row['weight'] / $row['container']->max_weight_g * 100)))
                                    <div class="mx-5 mb-2 h-1.5 overflow-hidden rounded-full bg-line-soft">
                                        <div class="h-full rounded-full {{ $row['over'] ? 'bg-warn' : 'bg-accent' }}" style="width: {{ $load }}%"></div>
                                    </div>
                                @endif
                                @if ($row['entries']->isEmpty())
                                    <p class="px-5 pb-4 text-sm text-muted">Noch leer.</p>
                                @else
                                    <ul class="divide-y divide-line-soft">
                                        @foreach ($row['entries'] as $entry)
                                            <x-packlist-row :trip="$trip" :entry="$entry" :containers="$tripContainers" :can-edit="$canEdit" view="containers" />
                                        @endforeach
                                    </ul>
                                @endif
                            </section>
                        @endforeach

                        @if ($unassigned->isNotEmpty())
                            @php($ids = $unassigned->pluck('id')->values())
                            <section class="card overflow-hidden border-dashed" x-show="!onlyOpen || openIn(@js($ids)) > 0">
                                <div class="flex items-center justify-between gap-3 px-5 pt-4 pb-2">
                                    <h2 class="card-title text-muted">Ohne Behältnis</h2>
                                    @if ($tripContainers->isNotEmpty())
                                        <button type="button" class="btn btn-ghost min-h-10 px-3 text-sm" @click="$dispatch('open-sheet', 'assign')">Zuordnen …</button>
                                    @endif
                                </div>
                                <ul class="divide-y divide-line-soft">
                                    @foreach ($unassigned as $entry)
                                        <x-packlist-row :trip="$trip" :entry="$entry" :containers="$tripContainers" :can-edit="$canEdit" view="containers" />
                                    @endforeach
                                </ul>
                            </section>
                        @endif
                    </div>
                @endif

                <div class="mt-4">
                    <p x-show="onlyOpen && done === total" x-cloak class="card p-6 text-center text-[15px] font-bold text-accent-ink">
                        Alles gepackt – gute Reise!
                    </p>
                </div>
            @endif

            @if ($trip->isGroup() && $canEdit)
                @include('trips._group-items')
            @endif

            @if ($canEdit)
            {{-- Nach dem Entfernen per X: ein paar Sekunden lang zurückholbar --}}
            <div x-cloak x-show="undo || undoError" x-transition.opacity role="status" aria-live="polite"
                 class="fixed inset-x-4 bottom-36 z-30 mx-auto flex max-w-md items-center gap-3 rounded-card bg-ink py-2 pr-2 pl-4 text-sm font-semibold text-white shadow-[0_20px_40px_-16px_rgba(23,33,30,0.5)] sm:bottom-24">
                <span class="min-w-0 flex-1 truncate" x-text="undoError || (undo && `„${undo.name}“ entfernt`)"></span>
                <button type="button" x-show="undo" @click="undoRemove()" class="min-h-11 shrink-0 rounded-chip px-3 font-extrabold text-accent-soft hover:bg-white/10">Rückgängig</button>
            </div>
            <button type="button" @click="$dispatch('open-sheet', 'add-entry')"
                    class="btn btn-primary fixed right-4 bottom-20 z-20 shadow-[0_12px_28px_-10px_rgba(14,107,94,0.6)] sm:bottom-6">
                <x-icon name="plus" size="18" /> Gegenstand
            </button>
            @endif
        </div>

        {{-- ───────────── Outfits ───────────── --}}
        <div class="mt-4" role="tabpanel" x-show="tab === 'outfits'" @if ($initialTab !== 'outfits') x-cloak @endif>
            @include('trips._outfits')
        </div>

        {{-- ───────────── Rückblick ───────────── --}}
        <div class="mt-4" role="tabpanel" x-show="tab === 'review'" @if ($initialTab !== 'review') x-cloak @endif>
            @if ($canEdit)
                @include('trips._review')
            @else
                <div class="card p-5 text-sm text-muted">Den Rückblick macht jede Person für ihre eigene Liste.</div>
            @endif
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
                    [$trip->transports?->first()?->icon() ?? 'compass', 'Anreise mit', $trip->transports?->map->label()->join(' + ')],
                    [$trip->accommodation?->icon() ?? 'bed', 'Unterkunft', $trip->accommodation?->label()],
                ], fn ($row) => filled($row[2])) as [$icon, $label, $value])
                    <div class="flex min-h-[52px] items-center gap-3 px-5 py-3">
                        <x-icon :name="$icon" size="18" class="text-muted" />
                        <span class="w-28 shrink-0 text-xs font-bold tracking-[0.06em] text-muted uppercase">{{ $label }}</span>
                        <span class="text-[15px] font-semibold">{{ $value }}</span>
                    </div>
                @endforeach
                @if ($stageLocations || $trip->external_url)
                    <div class="flex min-h-[52px] flex-wrap items-center gap-3 px-5 py-3">
                        <x-icon name="bike" size="18" class="text-muted" />
                        <span class="w-28 shrink-0 text-xs font-bold tracking-[0.06em] text-muted uppercase">{{ $stageLocations ? 'Etappen' : 'Geplant in' }}</span>
                        <span class="text-[15px] font-semibold">{{ $stageLocations ? collect($stageLocations)->unique()->join(' → ') : '„unterwegs“' }}</span>
                        @if ($trip->external_url)
                            <a href="{{ $trip->external_url }}" class="btn btn-ghost ml-auto min-h-10 px-3 text-sm" rel="noopener">In „unterwegs“ öffnen</a>
                        @endif
                    </div>
                @endif
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
                @if ($trip->isGroup() && $trip->members->count() > 1)
                    <form method="POST" action="{{ route('sharing.packlist', $trip) }}" class="px-5 py-4">
                        @csrf @method('PUT')
                        <p class="text-[15px] font-bold">Für mich mitpacken dürfen</p>
                        <p class="mt-0.5 text-sm text-muted">Wer hier ausgewählt ist, sieht deine Packliste und darf abhaken – z. B. wenn ihr gemeinsam packt. Ändern kannst nur du.</p>
                        <div class="mt-2 space-y-1">
                            @foreach ($trip->members->where('id', '!=', auth()->id()) as $member)
                                <label class="flex min-h-11 cursor-pointer items-center gap-3">
                                    <input type="checkbox" name="grantee_ids[]" value="{{ $member->id }}" @checked($myGrantees->contains($member->id)) class="size-5 accent-[var(--accent)]">
                                    <x-avatar :user="$member" size="sm" />
                                    <span class="text-[15px] font-semibold">{{ $member->name }}</span>
                                </label>
                            @endforeach
                        </div>
                        <button type="submit" class="btn btn-secondary mt-2 min-h-10 text-sm">Freigabe speichern</button>
                    </form>
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

    {{-- Kleidungsstück für einen Outfit-Slot --}}
    <x-sheet name="outfit-item" title="Kleidung hinzufügen">
        <p class="-mt-2 mb-3 text-sm font-semibold text-muted" x-text="$store.picker.label"></p>
        <div x-data="{ q: '' }">
            @if ($clothingItems->isNotEmpty())
                <form method="POST" :action="$store.picker.url" x-ref="pickForm" data-keep-scroll>
                    @csrf
                    <input type="hidden" name="item_id" x-ref="pickId">
                </form>
                <div class="relative">
                    <x-icon name="search" size="18" class="pointer-events-none absolute top-1/2 left-3.5 -translate-y-1/2 text-muted" />
                    <input type="search" x-model="q" autofocus placeholder="Kleidung suchen" aria-label="Kleidung suchen" class="input pl-10">
                </div>
                <ul class="mt-2 max-h-64 overflow-y-auto">
                    @foreach ($clothingItems as $item)
                        <li x-show="!q || @js(Str::lower($item->name)).includes(q.toLowerCase())">
                            <button type="button" @click="$refs.pickId.value = {{ $item->id }}; $refs.pickForm.submit()"
                                    class="flex min-h-[48px] w-full items-center gap-3 rounded-chip px-2 text-left hover:bg-line-soft">
                                <x-icon :name="$item->categoryGroup()->icon" size="16" class="text-muted" />
                                <span class="flex-1 text-[15px] font-semibold">{{ $item->name }}</span>
                                @if ($item->per_day)<span class="text-xs font-bold text-accent-ink">pro Tag</span>@endif
                            </button>
                        </li>
                    @endforeach
                </ul>
            @endif
            <form method="POST" :action="$store.picker.url" data-keep-scroll class="{{ $clothingItems->isNotEmpty() ? 'mt-4 border-t border-line-soft pt-4' : '' }}">
                @csrf
                <p class="label">Neues Kleidungsstück</p>
                <div class="flex flex-col gap-2 sm:flex-row">
                    <input name="new_name" required maxlength="120" :value="q" placeholder="z. B. Leinenhemd" aria-label="Name" class="input flex-1">
                    <select name="new_category" aria-label="Kategorie" class="input sm:w-48">
                        @foreach (\App\Enums\ItemCategory::clothing() as $case)
                            <option value="{{ $case->value }}">{{ $case->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="btn btn-secondary mt-3 w-full"><x-icon name="plus" size="18" /> Anlegen und hinzufügen</button>
            </form>
        </div>
    </x-sheet>

    {{-- Packliste exportieren --}}
    @php($exportBase = ['trip' => $trip] + ($listOwner->is(auth()->user()) ? [] : ['liste' => $listOwner->id]))
    <x-sheet name="export" title="Packliste exportieren">
        <div x-data="{
                onlyOpen: false, byContainer: false, text: '', notice: '',
                url(base, extra = {}) {
                    const query = new URLSearchParams({ ...(this.onlyOpen ? { offen: 1 } : {}), ...(this.byContainer ? { behaeltnisse: 1 } : {}), ...extra }).toString();
                    return query ? base + (base.includes('?') ? '&' : '?') + query : base;
                },
                async load() {
                    this.text = ''; this.notice = '';
                    try { this.text = await (await fetch(this.url(@js(route('packlist.export', $exportBase)), { format: 'txt' }))).text(); }
                    catch { this.notice = 'Offline – Teilen und Kopieren brauchen Verbindung.'; }
                },
                async share() {
                    try { await navigator.share({ title: @js(\App\Services\PacklistExport::title($trip, $listOwner, $listOwner->isNot(auth()->user()))), text: this.text }); }
                    catch (e) { if (e.name !== 'AbortError') this.copy(); }
                },
                async copy() {
                    try { await navigator.clipboard.writeText(this.text); this.notice = 'Kopiert – jetzt in einer Notiz einfügen.'; }
                    catch { this.notice = 'Kopieren ging nicht. Lade die Liste stattdessen als Datei.'; }
                },
             }"
             @open-sheet.window="if ($event.detail === 'export') load()">
            <div class="space-y-2">
                <label class="flex min-h-11 cursor-pointer items-center gap-3 text-[15px] font-bold">
                    <input type="checkbox" x-model="onlyOpen" @change="load()" class="size-5 accent-[var(--accent)]"> Nur offene Einträge
                </label>
                @if ($tripContainers->isNotEmpty())
                    <label class="flex min-h-11 cursor-pointer items-center gap-3 text-[15px] font-bold">
                        <input type="checkbox" x-model="byContainer" @change="load()" class="size-5 accent-[var(--accent)]"> Nach Behältnis ordnen
                    </label>
                @endif
            </div>

            <div class="mt-4 grid gap-2 sm:grid-cols-2">
                <a :href="url(@js(route('packlist.print', $exportBase)))" target="_blank" class="btn btn-primary"><x-icon name="printer" size="18" /> Drucken / PDF</a>
                <button type="button" x-show="navigator.share" @click="share()" :disabled="!text" class="btn btn-secondary"><x-icon name="share" size="18" /> An Notiz-App teilen</button>
                <button type="button" @click="copy()" :disabled="!text" class="btn btn-secondary"><x-icon name="copy" size="18" /> Als Text kopieren</button>
                <a :href="url(@js(route('packlist.export', $exportBase)), { format: 'txt', download: 1 })" class="btn btn-secondary"><x-icon name="download" size="18" /> Textdatei (.txt)</a>
                <a :href="url(@js(route('packlist.export', $exportBase)), { format: 'md', download: 1 })" class="btn btn-secondary"><x-icon name="download" size="18" /> Markdown (.md)</a>
            </div>

            <p x-show="notice" x-text="notice" x-cloak class="mt-3 text-sm font-bold text-accent-ink"></p>
            <p class="mt-4 text-sm text-muted">
                <strong>Drucken / PDF</strong> öffnet eine Druckansicht – dort „Als PDF sichern“ wählen.
                <strong>Teilen</strong> schickt die Liste z. B. an Apple Notizen oder Google Notizen.
                <strong>Markdown</strong> mit abhakbaren Kästchen passt zu Obsidian, Bear, Notion &amp; Co.
            </p>
        </div>
    </x-sheet>

    {{-- Behältnisse für diese Reise --}}
    <x-sheet name="containers" title="Behältnisse für diese Reise">
        @if ($userContainers->isNotEmpty())
            <form method="POST" action="{{ route('packlist.containers', $trip) }}">
                @csrf @method('PUT')
                <div class="space-y-2">
                    @foreach ($userContainers as $container)
                        <label class="flex min-h-[52px] cursor-pointer items-center gap-3 rounded-chip border border-line px-3 py-2 has-checked:border-accent has-checked:bg-accent-soft/50">
                            <input type="checkbox" name="container_ids[]" value="{{ $container->id }}" @checked($tripContainers->contains('id', $container->id)) class="size-5 accent-[var(--accent)]">
                            <x-icon :name="$container->kind->icon()" size="18" class="text-muted" />
                            <span class="flex-1 text-[15px] font-bold">{{ $container->name }}</span>
                            <span class="text-xs font-semibold text-muted">{{ $container->limitsLabel() }}</span>
                        </label>
                    @endforeach
                </div>
                <button type="submit" class="btn btn-primary mt-4 w-full">Übernehmen</button>
            </form>
        @endif
        <a href="{{ route('containers.index') }}" class="mt-3 block text-center text-sm font-bold text-accent-ink underline">Behältnisse verwalten</a>
    </x-sheet>

    {{-- Mehrere Einträge zuordnen --}}
    @if ($tripContainers->isNotEmpty() && $unassigned->isNotEmpty())
        <x-sheet name="assign" title="Zuordnen">
            <form method="POST" action="{{ route('packlist.assign', $trip) }}" x-data="{ selected: [] }">
                @csrf @method('PATCH')
                <label for="assign-container" class="label">In</label>
                <select id="assign-container" name="container_id" class="input">
                    @foreach ($tripContainers as $container)
                        <option value="{{ $container->id }}">{{ $container->name }}</option>
                    @endforeach
                </select>
                <ul class="mt-3 max-h-72 overflow-y-auto">
                    @foreach ($unassigned as $entry)
                        <li>
                            <label class="flex min-h-[48px] cursor-pointer items-center gap-3 rounded-chip px-2 hover:bg-line-soft">
                                <input type="checkbox" name="entry_ids[]" value="{{ $entry->id }}" x-model="selected" class="size-5 accent-[var(--accent)]">
                                <span class="flex-1 text-[15px] font-semibold">{{ $entry->name() }}</span>
                                <span class="text-xs font-bold text-muted">{{ $entry->quantity }}×</span>
                            </label>
                        </li>
                    @endforeach
                </ul>
                <button type="submit" class="btn btn-primary mt-4 w-full" :disabled="selected.length === 0">
                    <span x-text="selected.length > 1 ? selected.length + ' Einträge zuordnen' : 'Zuordnen'">Zuordnen</span>
                </button>
            </form>
        </x-sheet>
    @endif

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
                                <x-icon :name="$item->categoryGroup()->icon" size="16" class="text-muted" />
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
                        @foreach ($entryGroups as $choice)
                            <option value="{{ $choice->key }}" @selected($choice->is(PacklistGroup::Misc))>{{ $choice->label }}</option>
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
