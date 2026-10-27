<x-layouts.guest title="Admin-Anmeldung">
    <div class="card p-6">
        <h1 class="page-title">Admin-Anmeldung</h1>
        <p class="mt-3 text-[15px] leading-relaxed text-muted">Übergangslösung, bis die Anmeldung über BearID angebunden ist.</p>

        <form method="POST" action="{{ route('admin.login.store') }}" class="mt-6 space-y-4">
            @csrf
            <div>
                <label for="email" class="label">E-Mail-Adresse</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username" class="input">
                @error('email')<p class="field-error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="password" class="label">Passwort</label>
                <input id="password" name="password" type="password" required autocomplete="current-password" class="input">
            </div>
            <button type="submit" class="btn btn-dark w-full">Anmelden</button>
        </form>
    </div>
    <p class="mt-6 text-center text-sm">
        <a href="{{ route('login') }}" class="font-bold text-muted underline-offset-4 hover:underline">Zur Anmeldung per E-Mail-Link</a>
    </p>
</x-layouts.guest>
