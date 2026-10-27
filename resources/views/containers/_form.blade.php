@php
    use App\Enums\ContainerKind;

    $bagErrors = $errors->getBag($bag);
    $kg = fn (?int $grams) => $grams === null ? null : rtrim(rtrim(number_format($grams / 1000, 2, '.', ''), '0'), '.');
    $old = fn (string $field, $default) => $bagErrors->any() ? old($field) : $default;
    $id = $container->exists ? $container->id : 'new';
@endphp
<form method="POST" action="{{ $container->exists ? route('containers.update', $container) : route('containers.store') }}" class="space-y-3">
    @csrf
    @if ($container->exists) @method('PUT') @endif
    <div>
        <label for="name-{{ $id }}" class="label">Name</label>
        <input id="name-{{ $id }}" name="name" value="{{ $old('name', $container->name) }}" required maxlength="80" placeholder="z. B. Eurobox Küche" class="input">
        @error('name', $bag)<p class="field-error">{{ $message }}</p>@enderror
    </div>
    <fieldset>
        <legend class="label">Art</legend>
        <div class="flex flex-wrap gap-2">
            @foreach (ContainerKind::cases() as $kind)
                <label class="choice">
                    <input type="radio" name="kind" value="{{ $kind->value }}" class="sr-only" required @checked($old('kind', $container->kind?->value) === $kind->value)>
                    <x-icon :name="$kind->icon()" size="16" /> {{ $kind->label() }}
                </label>
            @endforeach
        </div>
    </fieldset>
    <div class="grid grid-cols-3 gap-2">
        <div>
            <label for="cap-{{ $id }}" class="label">Volumen l</label>
            <input id="cap-{{ $id }}" name="capacity_l" type="number" inputmode="numeric" min="1" max="1000" value="{{ $old('capacity_l', $container->capacity_l) }}" class="input">
        </div>
        <div>
            <label for="max-{{ $id }}" class="label">Max. kg</label>
            <input id="max-{{ $id }}" name="max_weight_kg" type="number" inputmode="decimal" step="0.1" min="0.1" max="500" value="{{ $old('max_weight_kg', $kg($container->max_weight_g)) }}" class="input">
        </div>
        <div>
            <label for="empty-{{ $id }}" class="label">Leer kg</label>
            <input id="empty-{{ $id }}" name="empty_weight_kg" type="number" inputmode="decimal" step="0.1" min="0" max="100" value="{{ $old('empty_weight_kg', $kg($container->empty_weight_g)) }}" class="input">
        </div>
    </div>
    @foreach (['capacity_l', 'max_weight_kg', 'empty_weight_kg'] as $field)
        @error($field, $bag)<p class="field-error">{{ $message }}</p>@enderror
    @endforeach
    <p class="text-xs font-semibold text-muted">Alles optional. Mit Maximalgewicht warnt die Packliste, wenn es zu schwer wird (z. B. 23 kg beim Fluggepäck).</p>
    <button type="submit" class="btn btn-primary">{{ $container->exists ? 'Speichern' : 'Anlegen' }}</button>
</form>
