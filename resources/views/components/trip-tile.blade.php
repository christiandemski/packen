@props(['trip', 'progress' => null])
@php
    $status = $trip->status();
    $others = $trip->members->where('id', '!=', auth()->id());
@endphp
<a href="{{ route('trips.show', $trip) }}" @class([
    'group card flex flex-col overflow-hidden transition hover:-translate-y-0.5 hover:shadow-[0_10px_30px_-12px_rgba(23,33,30,0.35)]',
    'opacity-80 hover:opacity-100' => $status === 'past',
])>
    <x-trip-cover :trip="$trip" class="h-48">
        <div class="absolute inset-0 bg-gradient-to-t from-black/75 via-black/20 to-black/10"></div>

        <div class="absolute inset-x-3 top-3 flex items-start justify-between gap-2">
            <span class="chip bg-white/95 text-ink">
                <x-icon :name="$trip->mode->icon()" size="14" /> {{ $trip->mode->label() }}
            </span>
            <span @class([
                'chip',
                'bg-accent text-white' => $status === 'running',
                'bg-white/95 text-ink' => $status === 'upcoming',
                'bg-black/55 text-white' => $status === 'past',
            ])>
                @if ($status === 'running')<span class="size-1.5 rounded-full bg-white"></span>@endif
                {{ $trip->countdownLabel() }}
            </span>
        </div>

        <div class="absolute inset-x-4 bottom-3.5 text-white">
            <span class="flex items-center gap-1.5 text-[11px] font-bold tracking-[0.08em] uppercase text-white/85">
                <x-icon :name="$trip->type->icon()" size="14" /> {{ $trip->type->label() }}
            </span>
            <h3 class="mt-1 text-[22px] leading-tight font-extrabold tracking-tight [text-shadow:0_1px_12px_rgba(0,0,0,0.3)]">{{ $trip->name }}</h3>
        </div>
    </x-trip-cover>

    <div class="flex flex-1 flex-col gap-2.5 p-4">
        <div class="space-y-1.5 text-sm font-semibold">
            <p class="flex items-center gap-2">
                <x-icon name="calendar" size="16" class="text-muted" />
                {{ $trip->dateRangeLabel() }} <span class="text-muted">· {{ $trip->days() }} {{ $trip->days() === 1 ? 'Tag' : 'Tage' }}</span>
            </p>
            @if ($trip->location)
                <p class="flex items-center gap-2"><x-icon name="map-pin" size="16" class="text-muted" /> <span class="truncate">{{ $trip->location }}</span></p>
            @endif
        </div>

        @if ($progress && $progress['total'] > 0 && $status !== 'past')
            @php($percent = (int) round($progress['packed'] / $progress['total'] * 100))
            <div>
                <div class="flex justify-between text-xs font-bold text-muted">
                    <span>{{ $progress['packed'] }} von {{ $progress['total'] }} gepackt</span>
                    <span class="text-accent-ink">{{ $percent }} %</span>
                </div>
                <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-line-soft">
                    <div class="h-full rounded-full bg-accent" style="width: {{ $percent }}%"></div>
                </div>
            </div>
        @endif

        <div class="mt-auto flex items-center justify-between gap-3 border-t border-line-soft pt-3">
            <div class="flex min-w-0 flex-wrap items-center gap-1.5">
                @if ($trip->transport)
                    <span class="chip bg-line-soft text-muted"><x-icon :name="$trip->transport->icon()" size="14" /> {{ $trip->transport->label() }}</span>
                @endif
                @if ($trip->accommodation)
                    <span class="chip bg-line-soft text-muted"><x-icon :name="$trip->accommodation->icon()" size="14" /> {{ $trip->accommodation->label() }}</span>
                @endif
            </div>
            @if ($trip->isGroup())
                <div class="flex shrink-0 -space-x-2" aria-label="{{ $trip->members->count() }} Mitreisende">
                    @foreach ($trip->members->take(4) as $member)
                        <x-avatar :user="$member" size="sm" class="ring-2 ring-surface" />
                    @endforeach
                    @if ($trip->members->count() > 4)
                        <span class="inline-flex size-7 items-center justify-center rounded-full bg-line-soft text-[11px] font-extrabold text-muted ring-2 ring-surface">+{{ $trip->members->count() - 4 }}</span>
                    @endif
                </div>
            @endif
        </div>
    </div>
</a>
