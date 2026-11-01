@php
    use App\Enums\Accommodation;
    use App\Enums\Transport;
    use App\Enums\TripMode;
    use App\Enums\TripType;
    use App\Http\Requests\TripRequest;
    use Illuminate\Support\Js;

    $editing = $trip->exists;
    $source ??= null;
    $mode = old('mode', $trip->mode?->value ?? 'solo');
    $selectedMembers = collect(old('members', $trip->relationLoaded('members') ? $trip->members->pluck('id')->all() : []))->map(fn ($id) => (int) $id);
    $coverTrip = $source ?? $trip;
    $coverUrl = $coverTrip->cover_path ? route('trips.cover', ['trip' => $coverTrip, 'v' => $coverTrip->updated_at?->timestamp]) : null;
    $title = $editing ? 'Reise bearbeiten' : ($source ? 'Reise duplizieren' : 'Neue Liste');
    $backUrl = $editing ? route('trips.show', $trip) : ($source ? route('trips.show', $source) : route('home'));
@endphp
<x-layouts.app :title="$title">
    <div class="mx-auto max-w-2xl">
        <div class="mb-6 flex items-center gap-2">
            <a href="{{ $backUrl }}" class="icon-btn -ml-2" aria-label="Zurück"><x-icon name="arrow-left" /></a>
            <h1 class="page-title">{{ $title }}</h1>
        </div>

        @if ($source)
            <p class="mb-4 flex items-start gap-2 rounded-chip bg-accent-soft px-4 py-3 text-sm font-semibold text-accent-ink">
                <x-icon name="copy" size="16" class="mt-0.5 shrink-0" />
                <span>Vorlage ist „{{ $source->name }}“. Trag den neuen Zeitraum ein – deine Packliste mit Modulen und Behältnissen wird übernommen, Abgehaktes startet wieder offen.</span>
            </p>
        @endif

        <form method="POST" action="{{ $editing ? route('trips.update', $trip) : route('trips.store') }}" enctype="multipart/form-data"
              class="space-y-4" x-data="{ mode: @js($mode) }">
            @csrf
            @if ($editing) @method('PUT') @endif
            @if ($source)<input type="hidden" name="source_trip" value="{{ $source->slug }}">@endif

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
                @php($typeKey = old('type', $trip->typeKey()))
                <fieldset x-data="{ type: @js($typeKey) }">
                    <div class="flex items-baseline justify-between gap-3">
                        <legend class="label">Reiseart</legend>
                        @if ($customTypes->isNotEmpty())
                            <a href="{{ route('trip-types.index') }}" class="text-xs font-bold text-muted hover:text-ink hover:underline">Eigene verwalten</a>
                        @endif
                    </div>
                    <div class="flex flex-wrap gap-2">
                        @foreach (TripType::cases() as $type)
                            <label class="choice">
                                <input type="radio" name="type" value="{{ $type->value }}" x-model="type" class="sr-only" required @checked($typeKey === $type->value)>
                                <x-icon :name="$type->icon()" size="16" /> {{ $type->label() }}
                            </label>
                        @endforeach
                        @foreach ($customTypes as $type)
                            <label class="choice">
                                <input type="radio" name="type" value="{{ $type->key() }}" x-model="type" class="sr-only" required @checked($typeKey === $type->key())>
                                <x-icon :name="$type->icon" size="16" /> {{ $type->name }}
                            </label>
                        @endforeach
                        <label class="choice border-dashed">
                            <input type="radio" name="type" value="{{ TripRequest::NEW_TYPE }}" x-model="type" class="sr-only" required @checked($typeKey === TripRequest::NEW_TYPE)>
                            <x-icon name="plus" size="16" /> Eigene
                        </label>
                    </div>
                    @error('type')<p class="field-error">{{ $message }}</p>@enderror

                    <div x-show="type === @js(TripRequest::NEW_TYPE)" x-cloak class="mt-3 space-y-3 rounded-chip bg-line-soft/60 p-4">
                        <div>
                            <label for="new_type_name" class="label">Name der Reiseart</label>
                            <input id="new_type_name" name="new_type_name" value="{{ old('new_type_name') }}" maxlength="60" placeholder="z. B. Geschäftsreise, Festival, Segeln" class="input" :disabled="type !== @js(TripRequest::NEW_TYPE)">
                            @error('new_type_name')<p class="field-error">{{ $message }}</p>@enderror
                        </div>
                        @include('trip-types._fields', ['prefix' => 'new_type_', 'icon' => old('new_type_icon', 'briefcase'), 'color' => old('new_type_color', 'pine'), 'disabled' => 'type !== '.Js::from(TripRequest::NEW_TYPE)])
                        <p class="text-xs font-semibold text-muted">Wird mit der Reise gespeichert und steht danach bei jeder Reise zur Auswahl.</p>
                    </div>
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
            @php($focus = [
                'x' => (int) old('cover_focus_x', $coverTrip->cover_focus_x ?? 50),
                'y' => (int) old('cover_focus_y', $coverTrip->cover_focus_y ?? 50),
                'zoom' => (int) old('cover_zoom', $coverTrip->cover_zoom ?? 100),
            ])
            <div class="card p-5" x-data="coverPicker(@js($coverUrl), @js($focus))">
                <p class="card-title">Titelbild <span class="text-sm font-semibold text-muted">(optional)</span></p>
                <p class="mt-1 text-sm text-muted">Erscheint in der Übersicht und oben auf der Reiseseite. Ohne Bild gibt es einen Farbverlauf passend zur Reiseart.</p>

                <div class="mt-4 overflow-hidden rounded-chip border border-line bg-line-soft">
                    {{-- Ganzes Bild; antippen oder ziehen legt den Punkt fest, der immer sichtbar bleibt --}}
                    <div x-show="preview" x-cloak class="p-3">
                        <div class="relative mx-auto w-fit cursor-crosshair touch-none select-none rounded-[8px] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent"
                             tabindex="0" role="group" aria-label="Bildmitte festlegen – Pfeiltasten verschieben"
                             @pointerdown="down" @pointermove="move" @pointerup="up" @pointercancel="up" @keydown="key">
                            <img x-ref="editor" :src="preview" alt="Titelbild" draggable="false" class="block max-h-72 max-w-full rounded-[8px]">
                            <span class="pointer-events-none absolute size-9 -translate-x-1/2 -translate-y-1/2 rounded-full border-[3px] border-white shadow-[0_0_0_1px_rgba(0,0,0,0.35),0_2px_10px_rgba(0,0,0,0.45)]" :style="`left: ${x}%; top: ${y}%`">
                                <span class="absolute top-1/2 left-1/2 size-1.5 -translate-x-1/2 -translate-y-1/2 rounded-full bg-white"></span>
                            </span>
                        </div>
                    </div>
                    <div x-show="!preview" class="flex h-44 flex-col items-center justify-center gap-2 text-muted">
                        <x-icon name="image" size="28" />
                        <span class="text-sm font-semibold">Kein Bild ausgewählt</span>
                    </div>
                </div>

                <div x-show="preview" x-cloak class="mt-3 space-y-3">
                    <p class="text-xs font-semibold text-muted">Tipp oder zieh auf den Punkt, der in jeder Ansicht zu sehen sein soll.</p>
                    <label class="flex items-center gap-3 text-sm font-bold">
                        <x-icon name="search" size="16" class="text-muted" /> Zoom
                        <input type="range" min="100" max="{{ TripRequest::MAX_COVER_ZOOM }}" step="5" x-model.number="zoom" class="min-w-0 flex-1 accent-[var(--accent)]" aria-label="Zoom">
                        <span class="w-12 text-right text-muted" x-text="`${zoom} %`"></span>
                    </label>
                    <input type="hidden" name="cover_focus_x" :value="x">
                    <input type="hidden" name="cover_focus_y" :value="y">
                    <input type="hidden" name="cover_zoom" :value="zoom">

                    {{-- Vorschau in den echten Seitenverhältnissen --}}
                    <div class="border-t border-line-soft pt-4">
                        <p class="label">Vorschau</p>
                        <div class="grid gap-x-3 gap-y-4 sm:grid-cols-2">
                            @foreach ([['Übersicht · Handy', 'aspect-[358/160]', 'w-[42%]'], ['Übersicht · Desktop', 'aspect-[552/184]', 'w-[46%]']] as [$caption, $ratio, $width])
                                <figure>
                                    <div class="flex {{ $ratio }} overflow-hidden rounded-[12px] border border-line bg-surface">
                                        <div class="relative {{ $width }} shrink-0 overflow-hidden">
                                            <img :src="preview" alt="" class="absolute inset-0 size-full object-cover" :style="imgStyle">
                                            <div class="absolute inset-0 bg-gradient-to-r from-transparent from-30% via-surface/60 via-65% to-surface"></div>
                                        </div>
                                        <div class="relative -ml-3 flex min-w-0 flex-1 flex-col gap-1.5 py-3 pr-3" aria-hidden="true">
                                            <span class="h-1.5 w-1/3 rounded-full bg-line"></span>
                                            <span class="h-2.5 w-3/4 rounded-full bg-ink/70"></span>
                                            <span class="h-1.5 w-2/3 rounded-full bg-line"></span>
                                            <span class="h-1.5 w-1/2 rounded-full bg-line"></span>
                                            <span class="mt-auto h-1 w-full rounded-full bg-accent/50"></span>
                                        </div>
                                    </div>
                                    <figcaption class="mt-1.5 text-xs font-semibold text-muted">{{ $caption }}</figcaption>
                                </figure>
                            @endforeach
                            @foreach ([['Reiseseite · Handy', 'aspect-[390/288]'], ['Reiseseite · Desktop', 'aspect-[672/288]']] as [$caption, $ratio])
                                <figure>
                                    <div class="relative {{ $ratio }} overflow-hidden rounded-[12px]">
                                        <img :src="preview" alt="" class="absolute inset-0 size-full object-cover" :style="imgStyle">
                                        <div class="absolute inset-0 bg-gradient-to-t from-black/85 via-black/40 via-45% to-black/25"></div>
                                        <div class="absolute inset-x-3 bottom-3 flex flex-col gap-1.5" aria-hidden="true">
                                            <span class="h-2 w-1/4 rounded-full bg-white/90"></span>
                                            <span class="h-3 w-2/3 rounded-full bg-white"></span>
                                            <span class="h-1.5 w-1/2 rounded-full bg-white/70"></span>
                                        </div>
                                    </div>
                                    <figcaption class="mt-1.5 text-xs font-semibold text-muted">{{ $caption }}</figcaption>
                                </figure>
                            @endforeach
                        </div>
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
                <a href="{{ $backUrl }}" class="btn btn-secondary">Abbrechen</a>
                <button type="submit" class="btn btn-primary">{{ $editing ? 'Speichern' : ($source ? 'Duplikat anlegen' : 'Liste anlegen') }}</button>
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
