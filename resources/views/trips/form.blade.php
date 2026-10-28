@php
    use App\Enums\Accommodation;
    use App\Enums\Transport;
    use App\Enums\TripMode;
    use App\Enums\TripType;

    $editing = $trip->exists;
    $mode = old('mode', $trip->mode?->value ?? 'solo');
    $selectedMembers = collect(old('members', $trip->exists ? $trip->members->pluck('id')->all() : []))->map(fn ($id) => (int) $id);
    $coverUrl = $trip->cover_path ? route('trips.cover', ['trip' => $trip, 'v' => $trip->updated_at?->timestamp]) : null;
@endphp
<x-layouts.app :title="$editing ? 'Reise bearbeiten' : 'Neue Liste'">
    <div class="mx-auto max-w-2xl">
        <div class="mb-6 flex items-center gap-2">
            <a href="{{ $editing ? route('trips.show', $trip) : route('home') }}" class="icon-btn -ml-2" aria-label="Zurück"><x-icon name="arrow-left" /></a>
            <h1 class="page-title">{{ $editing ? 'Reise bearbeiten' : 'Neue Liste' }}</h1>
        </div>

        <form method="POST" action="{{ $editing ? route('trips.update', $trip) : route('trips.store') }}" enctype="multipart/form-data"
              class="space-y-4" x-data="{ mode: @js($mode) }">
            @csrf
            @if ($editing) @method('PUT') @endif

            {{-- Art der Liste --}}
            <fieldset class="card p-5">
                <legend class="sr-only">Art der Liste</legend>
                <p class="card-title mb-3">Für wen ist die Liste?</p>
                <div class="grid grid-cols-2 gap-3">
                    @foreach ([[TripMode::Solo, 'user', 'Für mich', 'Privat – nur du siehst sie'], [TripMode::Group, 'users', 'Für eine Gruppe', 'Gemeinsam mit Freunden']] as [$case, $icon, $title, $hint])
                        <label class="flex cursor-pointer flex-col gap-2 rounded-card border-2 border-line p-4 transition hover:border-muted has-checked:border-accent has-checked:bg-accent-soft has-focus-visible:outline-2 has-focus-visible:outline-accent">
                            <input type="radio" name="mode" value="{{ $case->value }}" x-model="mode" class="sr-only" @checked($mode === $case->value)>
                            <x-icon :name="$icon" size="24" class="text-accent" />
                            <span class="text-[15px] font-bold">{{ $title }}</span>
                            <span class="text-xs font-semibold text-muted">{{ $hint }}</span>
                        </label>
                    @endforeach
                </div>
                @error('mode')<p class="field-error">{{ $message }}</p>@enderror

                <div x-show="mode === 'group'" x-cloak class="mt-5 border-t border-line-soft pt-4">
                    <p class="label">Mitreisende</p>
                    @forelse ($friends as $friend)
                        <label class="flex min-h-[52px] cursor-pointer items-center gap-3 rounded-chip px-2 hover:bg-line-soft">
                            <input type="checkbox" name="members[]" value="{{ $friend->id }}" @checked($selectedMembers->contains($friend->id)) class="size-5 rounded accent-[var(--accent)]">
                            <x-avatar :user="$friend" size="sm" />
                            <span class="text-[15px] font-semibold">{{ $friend->name }}</span>
                        </label>
                    @empty
                        <p class="rounded-chip bg-line-soft px-3 py-3 text-sm font-semibold text-muted">
                            Es gibt noch keine weiteren Personen in {{ config('app.name') }}.
                            @can('admin') <a href="{{ route('admin.index') }}" class="text-accent-ink underline">Freunde einladen</a> @endcan
                        </p>
                    @endforelse
                    @error('members.*')<p class="field-error">{{ $message }}</p>@enderror
                </div>
                @if ($editing && $trip->isGroup())
                    <p x-show="mode === 'solo'" x-cloak class="mt-3 text-xs font-semibold text-warn-ink">Beim Wechsel auf „Für mich“ werden alle Mitreisenden entfernt.</p>
                @endif
            </fieldset>

            {{-- Reisedaten --}}
            <div class="card space-y-4 p-5">
                <p class="card-title">Reise</p>
                @if ($trip->external_url)
                    <p class="rounded-chip bg-accent-soft px-4 py-3 text-sm font-semibold text-accent-ink">
                        Diese Reise kommt aus „unterwegs“. Etappen und Zeitraum änderst du am besten dort – sonst überschreibt die nächste Übertragung deine Änderung.
                        <a href="{{ $trip->external_url }}" class="font-bold underline" rel="noopener">In „unterwegs“ öffnen</a>
                    </p>
                @endif
                <div>
                    <label for="name" class="label">Name</label>
                    <input id="name" name="name" value="{{ old('name', $trip->name) }}" required maxlength="120" placeholder="z. B. Lissabon im Mai" class="input">
                    @error('name')<p class="field-error">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="location" class="label">Reiseziel <span class="normal-case tracking-normal">(optional)</span></label>
                    <input id="location" name="location" value="{{ old('location', $trip->location) }}" maxlength="120" placeholder="Stadt, Region oder Land" class="input">
                    @error('location')<p class="field-error">{{ $message }}</p>@enderror
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label for="start_date" class="label">Anreise</label>
                        <input id="start_date" name="start_date" type="date" value="{{ old('start_date', $trip->start_date?->toDateString()) }}" required class="input">
                        @error('start_date')<p class="field-error">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="end_date" class="label">Abreise</label>
                        <input id="end_date" name="end_date" type="date" value="{{ old('end_date', $trip->end_date?->toDateString()) }}" required class="input">
                        @error('end_date')<p class="field-error">{{ $message }}</p>@enderror
                    </div>
                </div>
                <fieldset>
                    <legend class="label">Reiseart</legend>
                    <div class="flex flex-wrap gap-2">
                        @foreach (TripType::cases() as $type)
                            <label class="choice">
                                <input type="radio" name="type" value="{{ $type->value }}" class="sr-only" required @checked(old('type', $trip->type?->value) === $type->value)>
                                <x-icon :name="$type->icon()" size="16" /> {{ $type->label() }}
                            </label>
                        @endforeach
                    </div>
                    @error('type')<p class="field-error">{{ $message }}</p>@enderror
                </fieldset>
            </div>

            {{-- Unterwegs --}}
            <div class="card space-y-4 p-5">
                <p class="card-title">Unterwegs</p>
                @php($selectedTransports = collect(old('transports', $trip->transports?->map->value->all() ?? [])))
                <fieldset>
                    <legend class="label">Anreise mit <span class="normal-case tracking-normal">(mehrere möglich, z. B. Flugzeug + Mietwagen)</span></legend>
                    <div class="flex flex-wrap gap-2">
                        @foreach (Transport::cases() as $case)
                            <label class="choice">
                                <input type="checkbox" name="transports[]" value="{{ $case->value }}" class="sr-only" @checked($selectedTransports->contains($case->value))>
                                <x-icon :name="$case->icon()" size="16" /> {{ $case->label() }}
                            </label>
                        @endforeach
                    </div>
                    @error('transports.*')<p class="field-error">{{ $message }}</p>@enderror
                </fieldset>
                @foreach ([['accommodation', 'Unterkunft', Accommodation::cases(), $trip->accommodation]] as [$field, $label, $cases, $current])
                    <fieldset>
                        <legend class="label">{{ $label }}</legend>
                        <div class="flex flex-wrap gap-2">
                            <label class="choice">
                                <input type="radio" name="{{ $field }}" value="" class="sr-only" @checked(old($field, $current?->value) === null || old($field) === '')>
                                Offen
                            </label>
                            @foreach ($cases as $case)
                                <label class="choice">
                                    <input type="radio" name="{{ $field }}" value="{{ $case->value }}" class="sr-only" @checked(old($field, $current?->value) === $case->value)>
                                    <x-icon :name="$case->icon()" size="16" /> {{ $case->label() }}
                                </label>
                            @endforeach
                        </div>
                        @error($field)<p class="field-error">{{ $message }}</p>@enderror
                    </fieldset>
                @endforeach
            </div>

            {{-- Titelbild --}}
            <div class="card p-5" x-data="coverPicker(@js($coverUrl))">
                <p class="card-title">Titelbild <span class="text-sm font-semibold text-muted">(optional)</span></p>
                <p class="mt-1 text-sm text-muted">Erscheint auf der Kachel. Ohne Bild gibt es einen Farbverlauf passend zur Reiseart.</p>

                <div class="mt-4 overflow-hidden rounded-chip border border-line bg-line-soft">
                    <template x-if="preview">
                        <img :src="preview" alt="Vorschau des Titelbilds" class="h-44 w-full object-cover">
                    </template>
                    <div x-show="!preview" class="flex h-44 flex-col items-center justify-center gap-2 text-muted">
                        <x-icon name="image" size="28" />
                        <span class="text-sm font-semibold">Kein Bild ausgewählt</span>
                    </div>
                </div>

                <div class="mt-3 flex flex-wrap gap-2">
                    <label class="btn btn-secondary cursor-pointer">
                        <x-icon name="image" size="18" /> Bild wählen
                        <input type="file" name="cover" accept="image/jpeg,image/png,image/webp" class="sr-only" x-ref="file" @change="pick">
                    </label>
                    <button type="button" class="btn btn-ghost" x-show="preview" @click="clear"><x-icon name="x" size="18" /> Entfernen</button>
                </div>
                <input type="hidden" name="remove_cover" :value="removed ? 1 : 0" value="0">
                <p class="mt-2 text-xs font-semibold text-muted">JPG, PNG oder WebP, höchstens 8 MB.</p>
                @error('cover')<p class="field-error">{{ $message }}</p>@enderror
            </div>

            <div class="flex flex-col-reverse gap-2 pt-2 sm:flex-row sm:justify-end">
                <a href="{{ $editing ? route('trips.show', $trip) : route('home') }}" class="btn btn-secondary">Abbrechen</a>
                <button type="submit" class="btn btn-primary">{{ $editing ? 'Speichern' : 'Liste anlegen' }}</button>
            </div>
        </form>

        @if ($editing && $trip->isGroup())
            @can('admin')
                <form method="POST" action="{{ route('trips.invite', $trip) }}" class="card mt-10 p-5">
                    @csrf
                    <p class="card-title">Neue Person einladen</p>
                    <p class="mt-1 text-sm text-muted">Für Leute ohne Konto: Sie bekommen eine Einladung per E-Mail und sind nach dem Annehmen direkt in dieser Reise.</p>
                    <div class="mt-4 grid gap-3 sm:grid-cols-[1fr_1.4fr_auto] sm:items-end">
                        <div>
                            <label for="invite-name" class="label">Name</label>
                            <input id="invite-name" name="name" value="{{ old('name') }}" required maxlength="100" class="input">
                            @error('name', 'invite')<p class="field-error">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="invite-email" class="label">E-Mail-Adresse</label>
                            <input id="invite-email" name="email" type="email" required class="input">
                            @error('email', 'invite')<p class="field-error">{{ $message }}</p>@enderror
                        </div>
                        <button type="submit" class="btn btn-secondary"><x-icon name="send" size="18" /> Einladen</button>
                    </div>
                </form>
            @endcan
        @endif

        @if ($editing)
            @can('delete', $trip)
                <div class="card mt-10 p-5" x-data="{ confirming: false }">
                    <p class="card-title">Reise löschen</p>
                    <p class="mt-1 text-sm text-muted">Löscht die Reise für alle Mitreisenden. Das lässt sich nicht rückgängig machen.</p>
                    <button type="button" class="btn btn-danger mt-4" x-show="!confirming" @click="confirming = true"><x-icon name="trash" size="18" /> Reise löschen</button>
                    <form method="POST" action="{{ route('trips.destroy', $trip) }}" x-show="confirming" x-cloak class="mt-4 flex flex-wrap gap-2">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn bg-warn text-white hover:bg-warn-ink">Ja, endgültig löschen</button>
                        <button type="button" class="btn btn-secondary" @click="confirming = false">Abbrechen</button>
                    </form>
                </div>
            @endcan
        @endif
    </div>
</x-layouts.app>
