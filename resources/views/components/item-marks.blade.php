@props(['item'])
{{-- Kleine Symbole statt Text in der Katalog-Zeile; die Legende steht über den Karten (App\Models\Item::marks). --}}
@foreach ($item->marks() as [$icon, $label, $accent])
    <span class="{{ $accent ? 'text-accent' : 'text-muted' }}" title="{{ $label }}"><x-icon :name="$icon" size="14" /><span class="sr-only">{{ $label }}</span></span>
@endforeach
