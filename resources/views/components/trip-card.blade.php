@props(['trip', 'progress' => null])
@php
    $status = $trip->status();
    $percent = $progress && $progress['total'] > 0 && in_array($status, ['upcoming', 'running'], true)
        ? (int) round($progress['packed'] / $progress['total'] * 100)
        : null;
    $members = $trip->members;
@endphp
{{-- Große Bild-Card für die nächste Reise: Text liegt unten auf dem Foto. --}}
<a href="{{ route('trips.show', $trip) }}" class="group block overflow-hidden rounded-card shadow-[0_18px_40px_-20px_rgba(23,33,30,0.55)] transition hover:-translate-y-0.5 hover:shadow-[0_22px_44px_-18px_rgba(23,33,30,0.6)]">
    <x-trip-cover :trip="$trip" :icon-size="160" class="h-60 sm:h-72">
        <div class="absolute inset-0 bg-gradient-to-t from-black/85 via-black/30 via-45% to-black/10"></div>

        <span class="chip absolute top-3 left-3 bg-white/95 text-ink">
            <x-icon :name="$trip->typeIcon()" size="14" /> {{ $trip->typeLabel() }}
        </span>
        <span @class([
            'chip absolute top-3 right-3 backdrop-blur',
            'bg-accent text-white' => $status === 'running',
            'bg-black/45 text-white' => $status === 'upcoming',
            'bg-warn-soft text-warn-ink' => $status === 'ended',
        ])>
            @if ($status === 'running')<span class="size-1.5 rounded-full bg-white"></span>@endif
            {{ $trip->countdownLabel() }}
        </span>

        <div class="absolute inset-x-4 bottom-4 text-white">
            <div class="flex items-end justify-between gap-3">
                <div class="min-w-0">
                    <h3 class="truncate text-2xl leading-tight font-extrabold tracking-tight [text-shadow:0_1px_12px_rgba(0,0,0,0.3)] sm:text-3xl">{{ $trip->name }}</h3>
                    <p class="mt-0.5 truncate text-sm font-semibold text-white/85">
                        {{ $trip->dateRangeShortLabel() }}@if ($trip->location) · {{ $trip->location }}@endif
                    </p>
                </div>
                @if ($trip->isGroup())
                    <div class="flex shrink-0 -space-x-2" aria-label="{{ $members->count() }} Mitreisende">
                        @foreach ($members->take(3) as $member)
                            <x-avatar :user="$member" size="sm" class="ring-2 ring-black/30" />
                        @endforeach
                        @if ($members->count() > 3)
                            <span class="inline-flex size-7 items-center justify-center rounded-full bg-white/90 text-[11px] font-extrabold text-ink ring-2 ring-black/30">+{{ $members->count() - 3 }}</span>
                        @endif
                    </div>
                @endif
            </div>

            @if ($percent !== null)
                <div class="mt-3 flex items-center gap-3">
                    <div class="h-1 flex-1 overflow-hidden rounded-full bg-white/25">
                        <div class="h-full rounded-full bg-white" style="width: {{ $percent }}%"></div>
                    </div>
                    <span class="shrink-0 text-xs font-bold text-white/85">{{ $progress['packed'] }} / {{ $progress['total'] }} gepackt</span>
                </div>
            @elseif ($status === 'ended')
                <p class="mt-3 text-xs font-bold text-white/85">Reise vorbei – Rückblick &amp; abhaken</p>
            @endif
        </div>
    </x-trip-cover>
</a>
