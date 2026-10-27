<x-layouts.guest title="Anmelden">
    <div class="card p-6">
        @if ($loginToken)
            <h1 class="page-title">Hallo {{ $loginToken->user->name }}</h1>
            <p class="mt-3 text-[15px] leading-relaxed text-muted">Ein Tipp noch, dann bist du angemeldet – auf diesem Gerät für 30 Tage.</p>
            <form method="POST" action="{{ route('login.consume', $token) }}" class="mt-6">
                @csrf
                <button type="submit" class="btn btn-primary w-full">Jetzt anmelden</button>
            </form>
        @else
            <h1 class="page-title">Link ungültig</h1>
            <p class="mt-3 text-[15px] leading-relaxed text-muted">Der Link ist abgelaufen oder wurde schon verwendet.</p>
            <a href="{{ route('login') }}" class="btn btn-primary mt-6 w-full">Neuen Link anfordern</a>
        @endif
    </div>
</x-layouts.guest>
