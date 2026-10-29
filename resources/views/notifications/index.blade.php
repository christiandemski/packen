<x-layouts.app title="Benachrichtigungen">
    <div class="mx-auto max-w-2xl">
        <div class="mb-5 flex items-center justify-between gap-3">
            <h1 class="page-title">Benachrichtigungen</h1>
            <a href="{{ route('notifications.settings') }}" class="btn btn-secondary min-h-10 text-sm"><x-icon name="settings" size="16" /> Einstellungen</a>
        </div>

        <div class="card overflow-hidden">
            @include('notifications._list', ['compact' => false])
        </div>
    </div>
</x-layouts.app>
