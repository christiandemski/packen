@php
    use App\Models\Category;

    $bagErrors = $errors->getBag($bag);
    $old = fn (string $field, $default) => $bagErrors->any() ? old($field) : $default;
    $id = $category->exists ? $category->id : 'new';
@endphp
<form method="POST" action="{{ $category->exists ? route('categories.update', $category) : route('categories.store') }}" class="space-y-3">
    @csrf
    @if ($category->exists) @method('PUT') @endif
    <div>
        <label for="name-{{ $id }}" class="label">Name</label>
        <input id="name-{{ $id }}" name="name" value="{{ $old('name', $category->name) }}" required maxlength="60" placeholder="z. B. Kamera, Hund, Angeln" class="input">
        @error('name', $bag)<p class="field-error">{{ $message }}</p>@enderror
    </div>
    <fieldset>
        <legend class="label">Icon</legend>
        <div class="grid grid-cols-6 gap-1.5 sm:grid-cols-7">
            @foreach (Category::ICONS as $icon)
                <label class="flex aspect-square cursor-pointer items-center justify-center rounded-chip border border-line text-muted hover:border-ink hover:text-ink has-checked:border-accent has-checked:bg-accent-soft has-checked:text-accent-ink has-focus-visible:ring-2 has-focus-visible:ring-accent">
                    <input type="radio" name="icon" value="{{ $icon }}" class="sr-only" required @checked($old('icon', $category->icon) === $icon) aria-label="{{ $icon }}">
                    <x-icon :name="$icon" size="22" />
                </label>
            @endforeach
        </div>
        @error('icon', $bag)<p class="field-error">{{ $message }}</p>@enderror
    </fieldset>
    <button type="submit" class="btn btn-primary">{{ $category->exists ? 'Speichern' : 'Anlegen' }}</button>
</form>
