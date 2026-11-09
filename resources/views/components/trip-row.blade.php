@props(['trip', 'progress' => null])
@php
    $status = $trip->status();
    $archived = $status === 'past';
@endphp
{{-- Kompakte Zeile mit Vorschaubild für alle weiteren und abgehakten Reisen. --}}
<a href="{{ route('trips.show', $trip) }}" @class([
    'group card flex items-center gap-3.5 p-3 pr-4 transition hover:-translate-y-0.5 hover:shadow-[0_10px_30px_-12px_rgba(23,33,30,0.35)]',
    'opacity-60 hover:opacity-100' => $archived,
])>
    <x-trip-cover :trip="$trip" :icon-size="40" @class(['size-14 shrink-0 rounded-chip', 'grayscale-[60%]' => $archived]) />

    <div class="min-w-0 flex-1">
        <h3 @class(['truncate text-[15px] leading-tight font-extrabold', 'text-muted' => $archived])>{{ $trip->name }}</h3>
        <p class="mt-0.5 truncate text-[13px] font-semibold text-muted">
            {{ $trip->dateRangeShortLabel() }}@if ($trip->location) · {{ $trip->location }}@endif
        </p>
        @unless ($archived)
            <p @class([
                'mt-0.5 flex items-center gap-1.5 text-xs font-bold',
                'text-accent-ink' => $status === 'running',
                'text-warn-ink' => $status === 'ended',
                'text-muted/80' => $status === 'upcoming',
            ])>
                @if ($status === 'running')<span class="size-1.5 rounded-full bg-accent"></span>@endif
                {{ $trip->countdownLabel() }}
                @if ($progress && $progress['total'] > 0 && $status !== 'ended')
                    <span class="font-semibold text-muted">· {{ $progress['packed'] }} / {{ $progress['total'] }} gepackt</span>
                @endif
            </p>
        @endunless
    </div>

    @if ($trip->isGroup())
        <x-icon name="users" size="16" class="shrink-0 text-muted" />
    @endif
    <x-icon name="chevron-right" size="18" class="shrink-0 text-muted transition group-hover:translate-x-0.5" />
</a>
