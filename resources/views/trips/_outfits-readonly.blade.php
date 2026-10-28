{{-- Freigegebene Outfits einer mitreisenden Person – nur lesen --}}
@if ($days->isEmpty() || $days->every(fn ($d) => $d->slots->every(fn ($s) => $s->items->isEmpty())))
    <div class="card p-5 text-sm text-muted">{{ $owner->name }} hat noch keine Outfits geplant.</div>
@else
    <div class="space-y-3">
        @foreach ($days as $day)
            @continue($day->slots->every(fn ($s) => $s->items->isEmpty()))
            <section class="card p-4">
                <p class="flex flex-wrap items-center gap-2 text-[15px] font-extrabold">
                    {{ $day->label() }}@if ($day->title)<span class="font-semibold text-muted">· {{ $day->title }}</span>@endif
                    @if ($day->laundry_day)<span class="chip bg-accent-soft text-accent-ink"><x-icon name="droplet" size="12" /> Waschtag</span>@endif
                </p>
                @if ($stageLocation = $day->location ?? $stageLocations[$day->date->toDateString()] ?? null)
                    <p class="mt-1 flex items-center gap-1.5 text-sm font-semibold text-muted"><x-icon name="map-pin" size="16" /> {{ $stageLocation }}</p>
                @endif
                @if ($dayWeather = $weather['days'][$day->date->toDateString()] ?? null)
                    <p class="mt-1 flex items-center gap-1.5 text-sm font-semibold text-muted"><x-icon :name="$dayWeather->icon()" size="16" /> {{ $dayWeather->label() }} · {{ $dayWeather->summary() }}</p>
                @endif
                @foreach ($day->slots as $slot)
                    @continue($slot->items->isEmpty())
                    <p class="mt-2 text-xs font-bold tracking-[0.06em] text-muted uppercase">{{ $slot->name }}</p>
                    <div class="mt-1 flex flex-wrap gap-1.5">
                        @foreach ($slot->items as $item)
                            <span class="chip bg-line-soft text-ink">{{ $item->name }}</span>
                        @endforeach
                    </div>
                @endforeach
            </section>
        @endforeach
    </div>
@endif
