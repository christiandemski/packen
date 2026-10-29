<x-layouts.app title="Profil">
    <div class="mx-auto max-w-2xl space-y-4">
        <div class="mb-2 flex items-center gap-4">
            <x-avatar :user="$user" size="lg" />
            <div class="min-w-0">
                <h1 class="page-title truncate">{{ $user->name }}</h1>
                <p class="truncate text-[15px] font-semibold text-muted">{{ $user->email }}</p>
            </div>
        </div>

        <div class="card p-5" x-data="avatarCropper">
            <p class="card-title">Bild</p>

            <div x-show="!on" class="mt-4 flex flex-wrap items-center gap-4">
                <x-avatar :user="$user" size="xl" />
                <div class="flex flex-wrap gap-2">
                    <label class="btn btn-secondary cursor-pointer">
                        <x-icon name="image" size="18" /> Bild wählen
                        <input type="file" accept="image/*" class="sr-only" @change="pick">
                    </label>
                    @if ($user->avatar_path)
                        <form method="POST" action="{{ route('profile.avatar') }}">
                            @csrf @method('PUT')
                            <input type="hidden" name="avatar" value="">
                            <button type="submit" class="btn btn-ghost">Entfernen</button>
                        </form>
                    @endif
                </div>
            </div>

            <form method="POST" action="{{ route('profile.avatar') }}" x-show="on" x-cloak @submit.prevent="submit" class="mt-2">
                @csrf @method('PUT')
                <input type="hidden" name="avatar" x-ref="data">
                <div x-ref="stage" tabindex="0" aria-label="Bildausschnitt, mit den Pfeiltasten verschieben"
                     @pointerdown="down" @pointermove="move" @pointerup="up" @pointercancel="up"
                     @wheel.prevent="wheel" @keydown="key"
                     class="relative mx-auto mt-3 mb-3 size-[220px] cursor-grab touch-none overflow-hidden rounded-full bg-line-soft outline-offset-4 select-none active:cursor-grabbing">
                    <img x-ref="img" alt="" draggable="false" class="pointer-events-none absolute top-0 left-0 max-w-none origin-top-left">
                </div>
                <input type="range" min="1" max="4" step="0.01" :value="z" @input="zoom(+$event.target.value)" aria-label="Bildausschnitt vergrößern" class="mx-auto block w-56 accent-[var(--accent)]">
                <p class="mt-2 text-center text-sm text-muted">Ziehen zum Verschieben, Regler oder zwei Finger zum Vergrößern.</p>
                <div class="mt-4 flex justify-center gap-2">
                    <button type="submit" class="btn btn-primary">Bild speichern</button>
                    <button type="button" class="btn btn-ghost" @click="cancel">Abbrechen</button>
                </div>
            </form>

            <p x-show="error" x-text="error" x-cloak class="field-error"></p>
            @error('avatar')<p class="field-error">{{ $message }}</p>@enderror
        </div>

        <form method="POST" action="{{ route('profile.update') }}" class="card space-y-4 p-5">
            @csrf @method('PATCH')
            <p class="card-title">Name</p>
            <div>
                <label for="name" class="label">So sehen dich die anderen</label>
                <input id="name" name="name" value="{{ old('name', $user->name) }}" required maxlength="100" class="input">
                @error('name')<p class="field-error">{{ $message }}</p>@enderror
            </div>
            <button type="submit" class="btn btn-primary">Speichern</button>
        </form>

        <div class="card p-5" x-data="{ open: {{ $errors->apiToken->any() ? 'true' : 'false' }} }">
            <p class="card-title">Zugänge für andere Apps</p>
            <p class="mt-1 text-sm text-muted">Damit kann z. B. „unterwegs“ Reisen in deinem Namen anlegen und den Packstand abfragen. Widerrufen geht jederzeit.</p>

            @if (session('api_token'))
                <div class="mt-4 rounded-chip bg-accent-soft p-4 text-accent-ink" x-data="{ copied: false }">
                    <p class="text-sm font-bold">Neuer Token – nur jetzt sichtbar, bitte gleich in der anderen App eintragen:</p>
                    <div class="mt-2 flex flex-wrap items-center gap-2">
                        <code x-ref="token" class="min-w-0 flex-1 rounded-[8px] bg-surface px-3 py-2 font-mono text-[13px] break-all text-ink">{{ session('api_token') }}</code>
                        <button type="button" class="btn btn-secondary min-h-10" @click="navigator.clipboard.writeText($refs.token.textContent); copied = true" x-text="copied ? 'Kopiert' : 'Kopieren'">Kopieren</button>
                    </div>
                </div>
            @endif

            @if ($apiTokens->isNotEmpty())
                <ul class="mt-4 divide-y divide-line-soft">
                    @foreach ($apiTokens as $apiToken)
                        <li class="flex items-center justify-between gap-3 py-3">
                            <div class="min-w-0">
                                <p class="truncate text-[15px] font-bold">{{ $apiToken->name }}</p>
                                <p class="text-xs font-semibold text-muted">
                                    angelegt {{ $apiToken->created_at->translatedFormat('j. F Y') }} ·
                                    {{ $apiToken->last_used_at ? 'zuletzt genutzt '.$apiToken->last_used_at->diffForHumans() : 'noch nie genutzt' }}
                                </p>
                            </div>
                            <form method="POST" action="{{ route('api-tokens.destroy', $apiToken) }}">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-ghost min-h-10 px-3 text-sm" aria-label="Zugang {{ $apiToken->name }} widerrufen">Widerrufen</button>
                            </form>
                        </li>
                    @endforeach
                </ul>
            @endif

            <button type="button" class="btn btn-secondary mt-4" x-show="!open" @click="open = true; $nextTick(() => $refs.tokenName.focus())"><x-icon name="plus" size="18" /> Zugang anlegen</button>
            <form method="POST" action="{{ route('api-tokens.store') }}" x-show="open" x-cloak class="mt-4 flex flex-wrap items-end gap-2">
                @csrf
                <div class="min-w-0 flex-1">
                    <label for="token-name" class="label">Wofür?</label>
                    <input id="token-name" x-ref="tokenName" name="name" value="{{ $errors->apiToken->any() ? old('name') : '' }}" required maxlength="60" placeholder="z. B. unterwegs" class="input">
                </div>
                <button type="submit" class="btn btn-primary">Anlegen</button>
            </form>
            @error('name', 'apiToken')<p class="field-error">{{ $message }}</p>@enderror
        </div>

        <a href="{{ route('notifications.settings') }}" class="card flex min-h-[64px] items-center gap-3 p-5 hover:bg-line-soft">
            <x-icon name="bell" size="20" class="text-accent" />
            <span class="min-w-0 flex-1">
                <span class="card-title block">Benachrichtigungen</span>
                <span class="mt-0.5 block text-sm text-muted">Push-Erinnerungen und E-Mails einstellen</span>
            </span>
            <x-icon name="chevron-right" size="16" class="text-muted" />
        </a>

        <form method="POST" action="{{ route('logout') }}" class="card flex items-center justify-between gap-4 p-5">
            @csrf
            <div>
                <p class="card-title">Abmelden</p>
                <p class="mt-1 text-sm text-muted">Beendet die Sitzung auf diesem Gerät.</p>
            </div>
            <button type="submit" class="btn btn-secondary"><x-icon name="log-out" size="18" /> Abmelden</button>
        </form>

        @can('deleteOwnAccount', $user)
            <div class="card p-5" x-data="{ open: {{ $errors->has('confirm') ? 'true' : 'false' }} }">
                <p class="card-title">Mein Konto löschen</p>
                <p class="mt-1 text-sm text-muted">
                    Entfernt deinen Namen, deine E-Mail-Adresse und deine privaten Reisen. Gemeinsame Reisen, die du organisierst,
                    gehen an eine mitreisende Person über.
                </p>
                <button type="button" class="btn btn-danger mt-4" x-show="!open" @click="open = true"><x-icon name="trash" size="18" /> Konto löschen …</button>
                <form method="POST" action="{{ route('profile.destroy') }}" x-show="open" x-cloak class="mt-4 space-y-3">
                    @csrf @method('DELETE')
                    <label class="flex items-start gap-3 text-sm font-semibold">
                        <input type="checkbox" name="confirm" value="1" class="mt-0.5 size-5 accent-[var(--warn)]">
                        Ja, ich möchte mein Konto endgültig löschen.
                    </label>
                    @error('confirm')<p class="field-error">{{ $message }}</p>@enderror
                    <div class="flex flex-wrap gap-2">
                        <button type="submit" class="btn bg-warn text-white hover:bg-warn-ink">Endgültig löschen</button>
                        <button type="button" class="btn btn-secondary" @click="open = false">Abbrechen</button>
                    </div>
                </form>
            </div>
        @endcan
    </div>
</x-layouts.app>
