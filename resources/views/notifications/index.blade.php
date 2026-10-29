<x-layouts.app title="Benachrichtigungen">
    <div class="mx-auto max-w-2xl">
        <h1 class="page-title mb-5">Benachrichtigungen</h1>

        <div class="card overflow-hidden">
            @include('notifications._list', ['compact' => false])
        </div>
        @if ($notifications->isNotEmpty())
            <p class="mt-3 text-center text-xs font-semibold text-muted">
                E-Mails zusätzlich? <a href="{{ route('profile.edit') }}" class="text-accent-ink underline">Im Profil einstellen</a>
            </p>
        @endif
    </div>
</x-layouts.app>
