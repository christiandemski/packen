{{-- Regen angesagt, aber kein Regenschutz auf der Liste oder im Outfit --}}
@if ($rainDays)
    <div class="mb-4 flex flex-wrap items-center gap-3 rounded-card bg-warn-soft px-4 py-3 text-warn-ink">
        <x-icon name="cloud-rain" size="22" class="shrink-0" />
        <div class="min-w-0 flex-1 basis-48">
            <p class="text-sm font-extrabold">Regen angesagt: {{ implode(', ', $rainDays) }}</p>
            <p class="text-xs font-semibold">Auf deiner Liste steht noch nichts gegen Regen – Regenjacke, Schirm oder Poncho?</p>
        </div>
        <form method="POST" action="{{ route('packlist.store', $trip) }}">
            @csrf
            @if ($rainGear)
                <input type="hidden" name="item_id" value="{{ $rainGear->id }}">
            @else
                <input type="hidden" name="custom_name" value="Regenjacke">
                <input type="hidden" name="group" value="{{ \App\Enums\PacklistGroup::Clothing->value }}">
            @endif
            <button type="submit" class="chip min-h-9 bg-warn text-white hover:opacity-90"><x-icon name="plus" size="12" /> {{ $rainGear?->name ?? 'Regenjacke' }}</button>
        </form>
    </div>
@endif
