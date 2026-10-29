{{-- Regen, Kälte oder Sonne angesagt, aber nichts dagegen auf der Liste oder im Outfit --}}
@foreach ($weatherHints as ['need' => $need, 'days' => $days, 'gear' => $gear])
    @php([$fallbackName, $fallbackGroup] = $need->fallback())
    <div class="mb-4 flex flex-wrap items-center gap-3 rounded-card bg-warn-soft px-4 py-3 text-warn-ink">
        <x-icon :name="$need->icon()" size="22" class="shrink-0" />
        <div class="min-w-0 flex-1 basis-48">
            <p class="text-sm font-extrabold">{{ $need->headline() }}: {{ implode(', ', $days) }}</p>
            <p class="text-xs font-semibold">{{ $need->hint() }}</p>
        </div>
        <form method="POST" action="{{ route('packlist.store', $trip) }}">
            @csrf
            @if ($gear)
                <input type="hidden" name="item_id" value="{{ $gear->id }}">
            @else
                <input type="hidden" name="custom_name" value="{{ $fallbackName }}">
                <input type="hidden" name="group" value="{{ $fallbackGroup->value }}">
            @endif
            <button type="submit" class="chip min-h-9 bg-warn text-white hover:opacity-90"><x-icon name="plus" size="12" /> {{ $gear?->name ?? $fallbackName }}</button>
        </form>
    </div>
@endforeach
