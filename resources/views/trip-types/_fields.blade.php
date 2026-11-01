{{-- Icon- und Farbwahl; $prefix z. B. „new_type_“ im Reiseformular, leer auf der Verwaltungsseite. --}}
@php
    use App\Models\CustomTripType;

    $prefix ??= '';
    $bag ??= 'default';
    $disabled ??= null;
@endphp
<fieldset>
    <legend class="label">Icon</legend>
    <div class="grid grid-cols-6 gap-1.5 sm:grid-cols-10">
        @foreach (CustomTripType::ICONS as $choice)
            <label class="flex aspect-square cursor-pointer items-center justify-center rounded-chip border border-line bg-surface text-muted hover:border-ink hover:text-ink has-checked:border-accent has-checked:bg-accent-soft has-checked:text-accent-ink has-focus-visible:ring-2 has-focus-visible:ring-accent">
                <input type="radio" name="{{ $prefix }}icon" value="{{ $choice }}" class="sr-only" @checked($icon === $choice) aria-label="{{ $choice }}" @if ($disabled) :disabled="{{ $disabled }}" @endif>
                <x-icon :name="$choice" size="20" />
            </label>
        @endforeach
    </div>
    @error($prefix.'icon', $bag)<p class="field-error">{{ $message }}</p>@enderror
</fieldset>
<fieldset>
    <legend class="label">Farbe <span class="normal-case tracking-normal">(für Reisen ohne eigenes Bild)</span></legend>
    <div class="flex flex-wrap gap-2">
        @foreach (CustomTripType::COLORS as $key => $gradient)
            <label class="size-10 cursor-pointer rounded-full ring-offset-2 ring-offset-surface has-checked:ring-2 has-checked:ring-ink has-focus-visible:ring-2 has-focus-visible:ring-accent" style="background: {{ $gradient }}">
                <input type="radio" name="{{ $prefix }}color" value="{{ $key }}" class="sr-only" @checked($color === $key) aria-label="Farbe {{ $key }}" @if ($disabled) :disabled="{{ $disabled }}" @endif>
            </label>
        @endforeach
    </div>
    @error($prefix.'color', $bag)<p class="field-error">{{ $message }}</p>@enderror
</fieldset>
