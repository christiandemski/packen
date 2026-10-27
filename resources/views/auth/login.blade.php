<x-layouts.guest title="Anmelden">
    @if (session('sent'))
        <div class="card p-6">
            <span class="mb-4 inline-flex size-12 items-center justify-center rounded-full bg-accent-soft text-accent-ink">
                <x-icon name="mail" size="24" />
            </span>
            <h1 class="page-title">Schau in dein Postfach</h1>
            <p class="mt-3 text-[15px] leading-relaxed text-muted">
                Wenn es für <strong class="text-ink">{{ session('sent') }}</strong> ein Konto gibt, ist ein Anmeldelink unterwegs.
                Er gilt 15 Minuten.
            </p>
            <a href="{{ route('login') }}" class="btn btn-secondary mt-6 w-full">Andere Adresse verwenden</a>
        </div>
    @else
        <div class="card p-6">
            <h1 class="page-title">Anmelden</h1>
            <p class="mt-3 text-[15px] leading-relaxed text-muted">Ohne Passwort: Wir schicken dir einen Link per E-Mail.</p>

            <form method="POST" action="{{ route('login.send') }}" class="mt-6 space-y-4">
                @csrf
                <div>
                    <label for="email" class="label">E-Mail-Adresse</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="email" inputmode="email" class="input">
                    @error('email')<p class="field-error">{{ $message }}</p>@enderror
                </div>
                <button type="submit" class="btn btn-primary w-full">
                    <x-icon name="send" size="18" /> Link anfordern
                </button>
            </form>
        </div>
        <p class="mt-6 text-center text-sm text-muted">
            Kein Konto? {{ config('app.name') }} funktioniert nur auf Einladung.
        </p>
        <p class="mt-2 text-center text-sm">
            <a href="{{ route('admin.login') }}" class="font-bold text-muted underline-offset-4 hover:underline">Admin-Anmeldung</a>
        </p>
    @endif
</x-layouts.guest>
