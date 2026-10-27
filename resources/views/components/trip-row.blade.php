@props(['trip'])
@php($status = $trip->status())
<a href="{{ route('trips.show', $trip) }}" @class([
    'flex min-h-[72px] items-center gap-3 px-3 py-2.5 transition hover:bg-line-soft',
    'opacity-80' => $status === 'past',
])>
    <x-trip-cover :trip="$trip" :icon-size="36" class="size-14 rounded-chip" />

    <div class="min-w-0 flex-1">
        <p class="truncate text-[15px] font-bold">{{ $trip->name }}</p>
        <p class="truncate text-[13px] font-semibold text-muted">
            {{ $trip->dateRangeLabel() }}@if ($trip->location) · {{ $trip->location }}@endif
        </p>
        <p class="mt-1 flex items-center gap-2 text-xs font-bold text-muted">
            <span class="inline-flex items-center gap-1"><x-icon :name="$trip->mode->icon()" size="13" /> {{ $trip->mode->label() }}</span>
            <span class="inline-flex items-center gap-1"><x-icon :name="$trip->type->icon()" size="13" /> {{ $trip->type->label() }}</span>
            @if ($trip->isGroup())<span>· {{ $trip->members->count() }} Personen</span>@endif
        </p>
    </div>

    <span @class([
        'chip hidden shrink-0 sm:inline-flex',
        'bg-accent text-white' => $status === 'running',
        'bg-accent-soft text-accent-ink' => $status === 'upcoming',
        'bg-line-soft text-muted' => $status === 'past',
    ])>{{ $trip->countdownLabel() }}</span>
    <x-icon name="chevron-right" size="18" class="text-muted" />
</a>
