@php
    $bagErrors = $errors->getBag($bag);
    $old = fn (string $field, $default) => $bagErrors->any() ? old($field) : $default;
    $id = $type->exists ? $type->id : 'new';
@endphp
<form method="POST" action="{{ $type->exists ? route('trip-types.update', $type) : route('trip-types.store') }}" class="space-y-3">
    @csrf
    @if ($type->exists) @method('PUT') @endif
    <div>
        <label for="name-{{ $id }}" class="label">Name</label>
        <input id="name-{{ $id }}" name="name" value="{{ $old('name', $type->name) }}" required maxlength="60" placeholder="z. B. Geschäftsreise, Festival, Segeln" class="input">
        @error('name', $bag)<p class="field-error">{{ $message }}</p>@enderror
    </div>
    @include('trip-types._fields', ['bag' => $bag, 'icon' => $old('icon', $type->icon), 'color' => $old('color', $type->color)])
    <button type="submit" class="btn btn-primary">{{ $type->exists ? 'Speichern' : 'Anlegen' }}</button>
</form>
