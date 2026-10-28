@props(['trip', 'progress' => null])
@php($status = $trip->status())
<a href="{{ route('trips.show', $trip) }}" @class([
    'flex min-h-[72px] items-center gap-3 px-3 py-2.5 transition hover:bg-line-soft',
    'opacity-80' => $status === 'past',
])>
    <x-trip-cover :trip="$trip" :icon-size="36" class="size-14 shrink-0 rounded-chip" />

    <div class="min-w-0 flex-1">
        <p class="truncate text-[15px] font-bold">{{ $trip->name }}</p>
        <p class="truncate text-[13px] font-semibold text-muted">
            {{ $trip->dateRangeLabel() }}@if ($trip->location) · {{ $trip->location }}@endif
        </p>
        {{-- Bricht auf schmalen Bildschirmen um, statt aus der Zeile zu laufen. --}}
        <p class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-0.5 text-xs font-bold text-muted">
            <span @class([
                'whitespace-nowrap sm:hidden',
                'text-accent-ink' => $status !== 'past',
            ])>{{ $trip->countdownLabel() }}</span>
            <span class="inline-flex items-center gap-1 whitespace-nowrap"><x-icon :name="$trip->mode->icon()" size="13" /> {{ $trip->isGroup() ? $trip->members->count().' Personen' : $trip->mode->label() }}</span>
            <span class="inline-flex items-center gap-1 whitespace-nowrap"><x-icon :name="$trip->type->icon()" size="13" /> {{ $trip->type->label() }}</span>
            @if ($progress && $progress['total'] > 0)<span class="inline-flex items-center gap-1 whitespace-nowrap text-accent-ink"><x-icon name="list-checks" size="13" /> {{ $progress['packed'] }}/{{ $progress['total'] }}</span>@endif
        </p>
    </div>

    <span @class([
        'chip hidden shrink-0 sm:inline-flex',
        'bg-accent text-white' => $status === 'running',
        'bg-accent-soft text-accent-ink' => $status === 'upcoming',
        'bg-line-soft text-muted' => $status === 'past',
    ])>{{ $trip->countdownLabel() }}</span>
    <x-icon name="chevron-right" size="18" class="shrink-0 text-muted" />
</a>
