<x-layouts.guest title="Einladung">
    <div class="card p-6">
        @if ($invitation)
            <span class="eyebrow">Einladung</span>
            <h1 class="page-title mt-1">Willkommen!</h1>
            <p class="mt-3 text-[15px] leading-relaxed text-muted">
                {{ $invitation->inviter?->name ?? 'Jemand' }} hat dich zu {{ config('app.name') }} eingeladen.
                Ein Passwort brauchst du nicht – künftig meldest du dich mit einem Link per E-Mail an
                (<strong class="text-ink">{{ $invitation->email }}</strong>).
            </p>
            <form method="POST" action="{{ route('invitation.accept', $token) }}" class="mt-6 space-y-4">
                @csrf
                <div>
                    <label for="name" class="label">Wie sollen dich die anderen sehen?</label>
                    <input id="name" name="name" value="{{ old('name', $invitation->name) }}" required maxlength="100" autocomplete="given-name" class="input">
                    @error('name')<p class="field-error">{{ $message }}</p>@enderror
                </div>
                <button type="submit" class="btn btn-primary w-full">Konto aktivieren</button>
            </form>
        @else
            <h1 class="page-title">Einladung ungültig</h1>
            <p class="mt-3 text-[15px] leading-relaxed text-muted">
                @switch($reason)
                    @case('accepted')
                        Diese Einladung wurde schon angenommen. Melde dich einfach mit deiner E-Mail-Adresse an.
                        @break
                    @case('expired')
                        Diese Einladung ist abgelaufen (sie gilt {{ \App\Services\InvitationService::VALID_DAYS }} Tage). Bitte um eine neue.
                        @break
                    @default
                        Dieser Link gilt nicht mehr. Wurde die Einladung erneut verschickt, funktioniert nur der Link aus der
                        <strong class="text-ink">neuesten</strong> E-Mail. Hast du schon ein Konto, melde dich einfach an.
                @endswitch
            </p>
            <a href="{{ route('login') }}" class="btn btn-primary mt-6 w-full">Zur Anmeldung</a>
        @endif
    </div>
</x-layouts.guest>
