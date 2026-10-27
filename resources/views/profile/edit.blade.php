<x-layouts.app title="Profil">
    <div class="mx-auto max-w-2xl space-y-4">
        <div class="mb-2 flex items-center gap-4">
            <x-avatar :user="$user" size="lg" />
            <div class="min-w-0">
                <h1 class="page-title truncate">{{ $user->name }}</h1>
                <p class="truncate text-[15px] font-semibold text-muted">{{ $user->email }}</p>
            </div>
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
