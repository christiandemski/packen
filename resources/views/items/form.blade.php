@php use App\Enums\ItemCategory; $editing = $item->exists; $clothing = collect(ItemCategory::clothing())->pluck('value')->all(); @endphp
<x-layouts.app :title="$editing ? $item->name : 'Neuer Gegenstand'">
    <div class="mx-auto max-w-2xl">
        <div class="mb-6 flex items-center gap-2">
            <a href="{{ route('items.index') }}" class="icon-btn -ml-2" aria-label="Zurück zum Katalog"><x-icon name="arrow-left" /></a>
            <h1 class="page-title truncate">{{ $editing ? $item->name : 'Neuer Gegenstand' }}</h1>
        </div>

        <form method="POST" action="{{ $editing ? route('items.update', $item) : route('items.store') }}" class="space-y-4"
              x-data="{ category: @js(old('category', $item->category_id ? App\Models\Category::PREFIX.$item->category_id : $item->category?->value)), clothing: @js($clothing), rain: @js((bool) old('rain_protection', $item->rain_protection)), rainTouched: @js($editing || old('name') !== null) }">
            @csrf
            @if ($editing) @method('PUT') @endif

            <div class="card space-y-4 p-5">
                <div>
                    <label for="name" class="label">Name</label>
                    <input id="name" name="name" value="{{ old('name', $item->name) }}" required maxlength="120" @unless($editing) autofocus @endunless placeholder="z. B. Regenjacke" class="input"
                           @input="rainTouched || (rain = new RegExp(@js(App\Support\RainGear::PATTERN), 'iu').test($event.target.value))">
                    @error('name')<p class="field-error">{{ $message }}</p>@enderror
                </div>

                <fieldset>
                    <legend class="label">Kategorie</legend>
                    <p class="mb-2 text-xs font-bold text-muted">Kleidung</p>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($categories->filter(fn ($g) => in_array($g->key, $clothing, true)) as $group)
                            <label class="choice">
                                <input type="radio" name="category" value="{{ $group->key }}" x-model="category" class="sr-only" required>
                                <x-icon :name="$group->icon" size="16" /> {{ $group->label }}
                            </label>
                        @endforeach
                    </div>
                    <p class="mt-4 mb-2 text-xs font-bold text-muted">Alles andere</p>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($categories->reject(fn ($g) => in_array($g->key, $clothing, true)) as $group)
                            <label class="choice">
                                <input type="radio" name="category" value="{{ $group->key }}" x-model="category" class="sr-only">
                                <x-icon :name="$group->icon" size="16" /> {{ $group->label }}
                            </label>
                        @endforeach
                        <a href="{{ route('categories.index') }}" class="choice border-dashed text-muted"><x-icon name="plus" size="16" /> Eigene Kategorie</a>
                    </div>
                    @error('category')<p class="field-error">{{ $message }}</p>@enderror
                </fieldset>
            </div>

            <div class="card divide-y divide-line-soft">
                <label class="flex min-h-[64px] cursor-pointer items-center gap-4 px-5 py-3">
                    <div class="flex-1">
                        <p class="text-[15px] font-bold">Pro Tag</p>
                        <p class="text-sm text-muted" x-show="clothing.includes(category)">Wird bis zum nächsten Waschtag täglich gebraucht, z. B. Socken.</p>
                        <p class="text-sm text-muted" x-show="!clothing.includes(category)" x-cloak>Ein Stück je Reisetag, z. B. Tageslinsen.</p>
                    </div>
                    <input type="checkbox" name="per_day" value="1" @checked(old('per_day', $item->per_day)) class="size-6 accent-[var(--accent)]">
                </label>
                <label class="flex min-h-[64px] cursor-pointer items-center gap-4 px-5 py-3">
                    <div class="flex-1">
                        <p class="text-[15px] font-bold">Am Abfahrtstag packen</p>
                        <p class="text-sm text-muted">Steht in der Packliste ganz oben, z. B. Ladekabel oder Zahnbürste.</p>
                    </div>
                    <input type="checkbox" name="pack_last" value="1" @checked(old('pack_last', $item->pack_last)) class="size-6 accent-[var(--accent)]">
                </label>
                <label class="flex min-h-[64px] cursor-pointer items-center gap-4 px-5 py-3">
                    <div class="flex-1">
                        <p class="text-[15px] font-bold">Schützt vor Regen</p>
                        <p class="text-sm text-muted">Z. B. Regenjacke, Schirm oder Softshell. Ist Regen angesagt und nichts davon dabei, weist die Packliste darauf hin.</p>
                    </div>
                    <input type="checkbox" name="rain_protection" value="1" x-model="rain" @change="rainTouched = true" class="size-6 accent-[var(--accent)]">
                </label>
            </div>

            <div class="card space-y-4 p-5">
                <div>
                    <label for="weight_g" class="label">Gewicht in Gramm <span class="normal-case tracking-normal">(optional)</span></label>
                    <input id="weight_g" name="weight_g" type="number" inputmode="numeric" min="0" max="100000" value="{{ old('weight_g', $item->weight_g) }}" class="input max-w-40">
                    @error('weight_g')<p class="field-error">{{ $message }}</p>@enderror
                </div>
                @if ($containers->isNotEmpty())
                    <div>
                        <label for="default_container_id" class="label">Standardplatz <span class="normal-case tracking-normal">(optional)</span></label>
                        <select id="default_container_id" name="default_container_id" class="input">
                            <option value="">Kein fester Platz</option>
                            @foreach ($containers as $container)
                                <option value="{{ $container->id }}" @selected((int) old('default_container_id', $item->default_container_id) === $container->id)>{{ $container->name }}</option>
                            @endforeach
                        </select>
                        <p class="mt-1.5 text-xs font-semibold text-muted">Kommt dieses Behältnis mit, landet der Gegenstand automatisch darin.</p>
                        @error('default_container_id')<p class="field-error">{{ $message }}</p>@enderror
                    </div>
                @endif
                <div>
                    <label for="notes" class="label">Notiz <span class="normal-case tracking-normal">(optional)</span></label>
                    <textarea id="notes" name="notes" rows="2" maxlength="500" class="input py-3" placeholder="z. B. die blaue, nicht die rote">{{ old('notes', $item->notes) }}</textarea>
                    @error('notes')<p class="field-error">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="flex flex-col-reverse gap-2 pt-2 sm:flex-row sm:justify-end">
                <a href="{{ route('items.index') }}" class="btn btn-secondary">Abbrechen</a>
                <button type="submit" class="btn btn-primary">{{ $editing ? 'Speichern' : 'Anlegen' }}</button>
            </div>
        </form>

        @if ($editing)
            <div class="card mt-10 p-5" x-data="{ confirming: false }">
                <p class="card-title">Gegenstand löschen</p>
                <p class="mt-1 text-sm text-muted">
                    @if ($item->templates->isNotEmpty())
                        Wird auch aus {{ $item->templates->count() === 1 ? 'der Vorlage' : 'den Vorlagen' }} {{ $item->templates->pluck('name')->map(fn ($n) => "„{$n}“")->join(', ', ' und ') }} entfernt.
                    @else
                        Steckt in keiner Vorlage.
                    @endif
                </p>
                <button type="button" class="btn btn-danger mt-4" x-show="!confirming" @click="confirming = true"><x-icon name="trash" size="18" /> Löschen</button>
                <form method="POST" action="{{ route('items.destroy', $item) }}" x-show="confirming" x-cloak class="mt-4 flex flex-wrap gap-2">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn bg-warn text-white hover:bg-warn-ink">Ja, löschen</button>
                    <button type="button" class="btn btn-secondary" @click="confirming = false">Abbrechen</button>
                </form>
            </div>
        @endif
    </div>
</x-layouts.app>
