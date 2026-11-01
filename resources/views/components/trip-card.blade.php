@props(['trip', 'progress' => null])
@php
    $status = $trip->status();
    $percent = $progress && $progress['total'] > 0 && in_array($status, ['upcoming', 'running'], true)
        ? (int) round($progress['packed'] / $progress['total'] * 100)
        : null;
    $members = $trip->members;
@endphp
{{-- Bild links, blendet nach rechts ins Weiß aus; Daten daneben. --}}
<a href="{{ route('trips.show', $trip) }}" @class([
    'group card relative flex h-40 overflow-hidden transition hover:-translate-y-0.5 hover:shadow-[0_10px_30px_-12px_rgba(23,33,30,0.35)] sm:h-46',
    'opacity-80 hover:opacity-100' => $status === 'past',
])>
    <x-trip-cover :trip="$trip" :icon-size="72" class="w-[42%] shrink-0 sm:w-[46%]">
        <div class="absolute inset-0 bg-gradient-to-r from-transparent from-30% via-surface/60 via-65% to-surface"></div>
        <span @class([
            'chip absolute top-3 left-3',
            'bg-accent text-white' => $status === 'running',
            'bg-white/95 text-ink' => $status === 'upcoming',
            'bg-warn-soft text-warn-ink' => $status === 'ended',
            'bg-black/55 text-white' => $status === 'past',
        ])>
            @if ($status === 'running')<span class="size-1.5 rounded-full bg-white"></span>@endif
            {{ $trip->countdownLabel() }}
        </span>
        <span class="chip absolute bottom-3 left-3 hidden bg-white/95 text-ink sm:inline-flex">
            <x-icon :name="$trip->mode->icon()" size="14" /> {{ $trip->mode->label() }}
        </span>
    </x-trip-cover>

    <div class="relative -ml-7 flex min-w-0 flex-1 flex-col gap-1 py-3.5 pr-4">
        <span class="eyebrow flex items-center gap-1.5"><x-icon :name="$trip->type->icon()" size="13" /> {{ $trip->type->label() }}</span>
        <h3 class="truncate text-lg leading-tight font-extrabold tracking-tight sm:text-xl">{{ $trip->name }}</h3>
        <p class="flex items-center gap-1.5 text-[13px] font-semibold whitespace-nowrap">
            <x-icon name="calendar" size="14" class="text-muted" />
            {{ $trip->dateRangeLabel() }}
            <span class="text-muted sm:hidden">· {{ $trip->days() }} {{ $trip->days() === 1 ? 'Tag' : 'Tage' }}</span>
            <span class="hidden text-muted sm:inline">· {{ $trip->durationLabel() }}</span>
        </p>
        @if ($trip->location)
            <p class="flex min-w-0 items-center gap-1.5 text-[13px] font-semibold"><x-icon name="map-pin" size="14" class="text-muted" /> <span class="truncate">{{ $trip->location }}</span></p>
        @endif

        <div class="mt-auto">
            @if ($percent !== null)
                <div class="flex justify-between text-xs font-bold text-muted">
                    <span>{{ $progress['packed'] }} von {{ $progress['total'] }} gepackt</span>
                    <span class="text-accent-ink">{{ $percent }} %</span>
                </div>
                <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-line-soft">
                    <div class="h-full rounded-full bg-accent" style="width: {{ $percent }}%"></div>
                </div>
            @elseif ($status === 'ended')
                <p class="text-xs font-bold text-warn-ink">Reise vorbei – Rückblick &amp; abhaken</p>
            @endif
        </div>

        <div class="flex items-center justify-between gap-2 border-t border-line-soft pt-2">
            <div class="flex min-w-0 items-center gap-1.5 overflow-hidden">
                @foreach ($trip->transports ?? [] as $transport)
                    <span class="chip shrink-0 bg-line-soft text-muted" title="{{ $transport->label() }}"><x-icon :name="$transport->icon()" size="14" /><span class="hidden lg:inline">{{ $transport->label() }}</span><span class="sr-only lg:hidden">{{ $transport->label() }}</span></span>
                @endforeach
                @if ($trip->accommodation)
                    <span class="chip hidden shrink-0 bg-line-soft text-muted sm:inline-flex" title="{{ $trip->accommodation->label() }}"><x-icon :name="$trip->accommodation->icon()" size="14" /><span class="sr-only">{{ $trip->accommodation->label() }}</span></span>
                @endif
            </div>
            @if ($trip->isGroup())
                <div class="flex shrink-0 -space-x-2" aria-label="{{ $members->count() }} Mitreisende">
                    @foreach ($members->take(3) as $member)
                        <x-avatar :user="$member" size="sm" class="ring-2 ring-surface" />
                    @endforeach
                    @if ($members->count() > 3)
                        <span class="inline-flex size-7 items-center justify-center rounded-full bg-line-soft text-[11px] font-extrabold text-muted ring-2 ring-surface">+{{ $members->count() - 3 }}</span>
                    @endif
                </div>
            @endif
        </div>
    </div>
</a>
