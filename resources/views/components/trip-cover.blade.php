@props(['trip', 'iconSize' => 96])
{{-- Eigenes Bild oder Verlauf je Reiseart mit großem Linien-Icon. --}}
<div {{ $attributes->merge(['class' => 'relative overflow-hidden']) }} style="background: {{ $trip->type->gradient() }}">
    @if ($trip->cover_path)
        <img src="{{ route('trips.cover', ['trip' => $trip, 'v' => $trip->updated_at?->timestamp]) }}" alt="" loading="lazy" decoding="async" class="absolute inset-0 size-full object-cover">
    @else
        <x-icon :name="$trip->type->icon()" :size="$iconSize" class="absolute -right-3 -bottom-4 text-white/15" :stroke-width="1.5" />
    @endif
    {{ $slot }}
</div>
