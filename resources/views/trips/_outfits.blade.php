@php
    use App\Enums\ItemCategory;
    use App\Models\OutfitSlot;

    $itemsById = $outfitDays->flatMap(fn ($d) => $d->slots->flatMap->items)->keyBy('id');
    $scroll = $outfitDays->count() > 7;
@endphp
<div x-data="{ day: @js($selectedDay), person: 'me' }" class="space-y-4">
    @if ($trip->isGroup())
        <div class="flex flex-wrap items-center justify-between gap-3">
            @if ($sharedOutfits->isNotEmpty())
                <div class="segment" role="group" aria-label="Wessen Outfits">
                    <button type="button" class="segment-item" :aria-pressed="person === 'me'" @click="person = 'me'">Meine</button>
                    @foreach ($sharedOutfits as $shared)
                        <button type="button" class="segment-item" :aria-pressed="person === {{ $shared['user']->id }}" @click="person = {{ $shared['user']->id }}">{{ $shared['user']->name }}</button>
                    @endforeach
                </div>
            @else
                <span></span>
            @endif
            <form method="POST" action="{{ route('sharing.outfits', $trip) }}">
                @csrf @method('PUT')
                <input type="hidden" name="shared" value="{{ $myOutfitsShared ? 0 : 1 }}">
                <button type="submit" role="switch" aria-checked="{{ $myOutfitsShared ? 'true' : 'false' }}" class="chip min-h-9 {{ $myOutfitsShared ? 'bg-accent text-white' : 'bg-line-soft text-muted hover:text-ink' }}">
                    <x-icon :name="$myOutfitsShared ? 'users' : 'lock'" size="14" /> {{ $myOutfitsShared ? 'Für Mitreisende sichtbar' : 'Meine Outfits sind privat' }}
                </button>
            </form>
        </div>

        @foreach ($sharedOutfits as $shared)
            <div x-show="person === {{ $shared['user']->id }}" x-cloak>
                @include('trips._outfits-readonly', ['days' => $shared['days'], 'owner' => $shared['user'], 'weather' => $shared['weather']])
            </div>
        @endforeach
    @endif

    <div x-show="person === 'me'" class="space-y-4">
    @if ($rainDays)
        <div class="-mb-4">@include('trips._rain-warning')</div>
    @endif
    {{-- Wochenübersicht --}}
    <section class="card overflow-hidden">
        <div class="flex items-center justify-between gap-3 px-5 pt-4 pb-3">
            <h2 class="card-title">Übersicht</h2>
            <span class="text-xs font-bold text-muted">{{ $outfitDays->count() }} Tage</span>
        </div>
        @if ($stageLocations && $trip->external_url)
            <p class="-mt-1 px-5 pb-2 text-xs font-semibold text-muted">
                Etappen kommen aus „unterwegs“ – <a href="{{ $trip->external_url }}" class="font-bold text-accent-ink underline" rel="noopener">dort ändern</a>.
            </p>
        @endif
        @if ($weather['place'] || $weather['hint'])
            <p class="-mt-1 px-5 pb-3 text-xs font-semibold text-muted">
                @if ($weather['days'])Wetter für {{ $weather['place'] }}.@endif {{ $weather['hint'] }}
            </p>
        @endif
        <div class="{{ $scroll ? 'overflow-x-auto' : '' }}">
            <table class="w-full border-collapse text-sm">
                <thead>
                    <tr>
                        <th class="sticky left-0 z-10 bg-surface px-3 text-left"><span class="sr-only">Kleidungsstück</span></th>
                        @foreach ($outfitDays as $outfitDay)
                            @php($date = $outfitDay->date->toDateString())
                            @php($dayWeather = $weather['days'][$date] ?? null)
                            <th class="px-0.5 pb-2">
                                <button type="button" @click="day = @js($date)" :aria-pressed="day === @js($date)"
                                        class="flex {{ $weather['days'] ? 'min-h-15' : 'min-h-12' }} w-10 flex-col items-center justify-center rounded-chip text-xs font-bold transition"
                                        :class="day === @js($date) ? 'bg-ink text-white' : '{{ $outfitDay->laundry_day ? 'bg-accent-soft text-accent-ink' : 'bg-line-soft text-muted hover:text-ink' }}'"
                                        aria-label="{{ $outfitDay->label() }}{{ $dayWeather ? ', '.$dayWeather->label().', bis '.$dayWeather->max.' °C' : '' }}{{ $outfitDay->laundry_day ? ', Waschtag' : '' }}">
                                    <span>{{ $outfitDay->date->locale('de')->minDayName }}</span>
                                    <span class="text-[15px] font-extrabold">{{ $outfitDay->date->day }}</span>
                                    @if ($weather['days'])
                                        <span class="text-[11px] font-bold">{{ $dayWeather ? $dayWeather->max.'°' : '–' }}</span>
                                    @endif
                                </button>
                            </th>
                        @endforeach
                        <th class="px-3 pb-2 text-right text-xs font-bold text-muted">Σ</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($outfitOverview as $category => $rows)
                        <tr><td colspan="{{ $outfitDays->count() + 2 }}" class="sticky left-0 bg-surface px-3 pt-3 pb-1 text-[11px] font-bold tracking-[0.08em] text-muted uppercase">{{ ItemCategory::from($category)->label() }}</td></tr>
                        @foreach ($rows as $row)
                            @php($single = $row['total'] === 1 && $outfitDays->count() > 1)
                            <tr class="{{ $single ? 'bg-warn-soft' : '' }}" @if ($single) title="Nur einmal getragen – streichen oder kombinieren?" @endif>
                                <td class="sticky left-0 z-10 max-w-36 truncate px-3 py-1.5 font-semibold {{ $single ? 'bg-warn-soft text-warn-ink' : 'bg-surface' }}">{{ $row['item']->name }}</td>
                                @foreach ($outfitDays as $index => $outfitDay)
                                    <td class="py-1.5 text-center">
                                        @if ($count = $row['worn'][$index] ?? 0)
                                            <span class="inline-flex size-5 items-center justify-center rounded-full text-[10px] font-extrabold text-white {{ $single ? 'bg-warn' : 'bg-accent' }}">{{ $count > 1 ? $count : '' }}</span>
                                        @endif
                                    </td>
                                @endforeach
                                <td class="px-3 py-1.5 text-right font-extrabold {{ $single ? 'text-warn-ink' : '' }}">{{ $row['total'] }}</td>
                            </tr>
                        @endforeach
                    @empty
                        <tr><td colspan="{{ $outfitDays->count() + 2 }}" class="px-5 py-6 text-center text-sm text-muted">Noch keine Outfits. Tippe einen Tag an und füge Kleidung hinzu.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <p class="flex flex-wrap gap-x-4 gap-y-1 border-t border-line-soft px-5 py-3 text-xs font-semibold text-muted">
            <span class="inline-flex items-center gap-1.5"><span class="size-2.5 rounded-full bg-accent"></span> getragen</span>
            <span class="inline-flex items-center gap-1.5"><span class="size-2.5 rounded-sm bg-accent-soft ring-1 ring-accent/30"></span> Waschtag</span>
            <span class="inline-flex items-center gap-1.5"><span class="size-2.5 rounded-full bg-warn"></span> nur 1× – streichen oder kombinieren?</span>
        </p>
    </section>

    {{-- Tagesdetails --}}
    @foreach ($outfitDays as $outfitDay)
        @php($date = $outfitDay->date->toDateString())
        @php($dayWeather = $weather['days'][$date] ?? null)
        <section class="card p-5" x-show="day === @js($date)" @if ($date !== $selectedDay) x-cloak @endif>
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0 flex-1">
                <form method="POST" action="{{ route('outfits.day', [$trip, $outfitDay]) }}" data-keep-scroll x-data="{ editing: false }">
                    @csrf @method('PATCH')
                    <p class="text-lg font-extrabold">
                        {{ $outfitDay->label() }}@if ($outfitDay->title)<span x-show="!editing"> · {{ $outfitDay->title }}</span>@endif
                    </p>
                    <button type="button" x-show="!editing" @click="editing = true; $nextTick(() => $refs.title.focus())" class="text-sm font-bold text-muted hover:text-ink">
                        {{ $outfitDay->title ? 'Titel ändern' : '+ Titel, z. B. Alfama & Belém' }}
                    </button>
                    <div x-show="editing" x-cloak class="mt-2 flex gap-2">
                        <input x-ref="title" name="title" value="{{ $outfitDay->title }}" maxlength="120" placeholder="Was steht an?" aria-label="Titel des Tages" class="input">
                        <button type="submit" class="btn btn-primary">OK</button>
                    </div>
                </form>

                {{-- Ort des Tages: eigener Ort (z. B. Tagesausflug) vor Etappe vor Reiseziel --}}
                @php($baseLocation = $stageLocations[$date] ?? $trip->location)
                <div x-data="{ editing: false }" class="mt-1">
                    <div x-show="!editing" class="flex flex-wrap items-center gap-x-2 gap-y-0.5 text-sm font-semibold text-muted">
                        @if ($outfitDay->location)
                            <span class="flex min-w-0 items-center gap-1.5 text-ink"><x-icon name="map-pin" size="16" class="shrink-0" /> <span class="truncate">{{ $outfitDay->location }}</span></span>
                            @if ($baseLocation)<span class="text-xs">statt {{ $baseLocation }}</span>@endif
                            <form method="POST" action="{{ route('outfits.day', [$trip, $outfitDay]) }}" data-keep-scroll>
                                @csrf @method('PATCH')
                                <input type="hidden" name="location" value="">
                                <button type="submit" class="text-xs font-bold text-muted underline hover:text-ink">zurücksetzen</button>
                            </form>
                        @elseif ($stageLocations[$date] ?? null)
                            <span class="flex items-center gap-1.5"><x-icon name="map-pin" size="16" /> {{ $stageLocations[$date] }}</span>
                        @endif
                        <button type="button" @click="editing = true; $nextTick(() => $refs.location.focus())" class="font-bold hover:text-ink">
                            {{ $outfitDay->location ? 'Ort ändern' : '+ Anderer Ort, z. B. Tagesausflug' }}
                        </button>
                    </div>
                    <form x-show="editing" x-cloak method="POST" action="{{ route('outfits.day', [$trip, $outfitDay]) }}" data-keep-scroll class="mt-1 flex gap-2">
                        @csrf @method('PATCH')
                        <input x-ref="location" name="location" value="{{ $outfitDay->location }}" maxlength="120" placeholder="{{ $baseLocation ? 'statt '.$baseLocation : 'z. B. Sintra' }}" aria-label="Ort an diesem Tag" class="input">
                        <button type="submit" class="btn btn-primary">OK</button>
                    </form>
                </div>

                @if ($dayWeather)
                    <p class="mt-1 flex items-center gap-1.5 text-sm font-semibold {{ $dayWeather->isRainy() && $rainDays ? 'text-warn-ink' : 'text-muted' }}">
                        <x-icon :name="$dayWeather->icon()" size="16" /> {{ $dayWeather->label() }} · {{ $dayWeather->summary() }}
                    </p>
                @endif
                </div>
                <form method="POST" action="{{ route('outfits.day', [$trip, $outfitDay]) }}" data-keep-scroll>
                    @csrf @method('PATCH')
                    <input type="hidden" name="laundry_day" value="{{ $outfitDay->laundry_day ? 0 : 1 }}">
                    <button type="submit" role="switch" aria-checked="{{ $outfitDay->laundry_day ? 'true' : 'false' }}" class="chip min-h-9 {{ $outfitDay->laundry_day ? 'bg-accent text-white' : 'bg-line-soft text-muted hover:text-ink' }}">
                        <x-icon name="droplet" size="14" /> Waschtag
                    </button>
                </form>
            </div>

            <div class="mt-4 space-y-4">
                @foreach ($outfitDay->slots as $slot)
                    <div>
                        <div class="mb-2 flex items-center justify-between">
                            <p class="text-xs font-bold tracking-[0.06em] text-muted uppercase">{{ $slot->name }}</p>
                            @if ($outfitDay->slots->count() > 1)
                                <form method="POST" action="{{ route('outfits.slots.destroy', [$trip, $slot]) }}" data-keep-scroll>
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-xs font-bold text-muted hover:text-warn-ink" aria-label="Slot {{ $slot->name }} entfernen">Entfernen</button>
                                </form>
                            @endif
                        </div>
                        <div class="flex flex-wrap gap-2">
                            @foreach ($slot->items as $item)
                                <form method="POST" action="{{ route('outfits.items.destroy', [$trip, $slot, $item]) }}" data-keep-scroll>
                                    @csrf @method('DELETE')
                                    <button type="submit" class="chip min-h-9 bg-ink text-white hover:bg-black" aria-label="{{ $item->name }} aus {{ $slot->name }} entfernen">
                                        {{ $item->name }} <x-icon name="x" size="12" />
                                    </button>
                                </form>
                            @endforeach
                            <button type="button" class="chip min-h-9 border border-dashed border-muted/60 text-muted hover:border-ink hover:text-ink"
                                    @click="$store.picker.url = @js(route('outfits.items.store', [$trip, $slot])); $store.picker.label = @js($slot->name.' · '.$outfitDay->label()); $dispatch('open-sheet', 'outfit-item')"
                                    aria-label="Teil zu {{ $slot->name }} hinzufügen">
                                <x-icon name="plus" size="12" /> Teil
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="mt-5 flex flex-wrap items-center gap-2 border-t border-line-soft pt-4">
                @foreach (array_diff(OutfitSlot::SUGGESTIONS, $outfitDay->slots->pluck('name')->all()) as $suggestion)
                    <form method="POST" action="{{ route('outfits.slots.store', [$trip, $outfitDay]) }}" data-keep-scroll>
                        @csrf
                        <input type="hidden" name="name" value="{{ $suggestion }}">
                        <button type="submit" class="chip min-h-9 bg-line-soft text-muted hover:text-ink"><x-icon name="plus" size="12" /> {{ $suggestion }}</button>
                    </form>
                @endforeach
                @unless ($loop->first)
                    <form method="POST" action="{{ route('outfits.copy', [$trip, $outfitDay]) }}" data-keep-scroll class="ml-auto">
                        @csrf
                        <button type="submit" class="chip min-h-9 bg-line-soft text-muted hover:text-ink"><x-icon name="repeat" size="12" /> Wie am Vortag</button>
                    </form>
                @endunless
            </div>
        </section>
    @endforeach

    {{-- Übernehmen --}}
    <section class="card p-5">
        <p class="card-title">Mengen für die Packliste</p>
        @if ($outfitQuantities)
            <p class="mt-2 text-sm font-semibold text-muted">
                {{ collect($outfitQuantities)->map(fn ($qty, $id) => $qty.'× '.$itemsById[$id]->name)->sortBy(fn ($label) => mb_strtolower(preg_replace('/^\d+× /', '', $label)))->join(', ') }}
            </p>
            <p class="mt-2 text-xs font-semibold text-muted">„Pro Tag“-Teile reichen jeweils bis zum nächsten Waschtag. Von Hand geänderte Mengen in der Packliste bleiben erhalten.</p>
        @else
            <p class="mt-1 text-sm text-muted">Sobald Outfits geplant sind, steht hier, wie viel du wovon brauchst.</p>
        @endif
        <form method="POST" action="{{ route('outfits.apply', $trip) }}" class="mt-4">
            @csrf
            <button type="submit" class="btn btn-primary w-full sm:w-auto" @disabled(! $outfitQuantities)><x-icon name="list-checks" size="18" /> In die Packliste übernehmen</button>
        </form>
    </section>
    </div>
</div>
