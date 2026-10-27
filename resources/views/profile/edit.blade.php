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
            <label class="flex cursor-pointer items-start gap-3">
                <input type="checkbox" name="email_notifications" value="1" @checked(old('email_notifications', $user->email_notifications)) class="mt-0.5 size-5 accent-[var(--accent)]">
                <span>
                    <span class="block text-[15px] font-bold">Benachrichtigungen auch per E-Mail</span>
                    <span class="block text-sm text-muted">z. B. wenn jemand in der Gruppe fragt oder etwas für dich mitbringt. In der App siehst du sie immer.</span>
                </span>
            </label>
            <button type="submit" class="btn btn-primary">Speichern</button>
        </form>

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
