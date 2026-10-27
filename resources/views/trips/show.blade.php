<x-layouts.app :title="$trip->name">
    <div class="mx-auto max-w-2xl" x-data="{ tab: 'packlist' }">
        <x-trip-cover :trip="$trip" :icon-size="140" class="-mx-4 -mt-6 mb-5 h-44 sm:mx-0 sm:mt-0 sm:rounded-card">
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

        <div class="mt-4 space-y-4">
            <div class="card p-5" role="tabpanel" x-show="tab === 'packlist'">
                <p class="card-title">Packliste</p>
                <p class="mt-1 text-sm text-muted">Module, Abhaken und Fortschritt kommen mit Meilenstein 3.</p>
            </div>
            <div class="card p-5" role="tabpanel" x-show="tab === 'outfits'" x-cloak>
                <p class="card-title">Outfits</p>
                <p class="mt-1 text-sm text-muted">Der Outfit-Planer mit Wochenübersicht kommt mit Meilenstein 4.</p>
            </div>
            <div class="card p-5" role="tabpanel" x-show="tab === 'review'" x-cloak>
                <p class="card-title">Rückblick</p>
                <p class="mt-1 text-sm text-muted">Nach der Reise markierst du hier, was gefehlt hat oder unnötig war (Meilenstein 6).</p>
            </div>

            <div class="card divide-y divide-line-soft">
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
            </div>

            @if ($trip->isGroup())
                <div class="card p-5">
                    <p class="card-title">Mitreisende · {{ $trip->members->count() }}</p>
                    <ul class="mt-3 space-y-1">
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
                </div>
            @endif
        </div>
    </div>
</x-layouts.app>
