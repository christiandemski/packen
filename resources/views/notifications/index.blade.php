<x-layouts.app title="Benachrichtigungen">
    <div class="mx-auto max-w-2xl">
        <h1 class="page-title mb-5">Benachrichtigungen</h1>

        @if ($notifications->isEmpty())
            <div class="card flex flex-col items-center px-6 py-12 text-center">
                <span class="inline-flex size-14 items-center justify-center rounded-full bg-accent-soft text-accent-ink"><x-icon name="bell" size="26" /></span>
                <p class="mt-4 text-lg font-extrabold">Alles ruhig</p>
                <p class="mt-1 text-[15px] text-muted">Hier erscheint, wenn jemand in der Gruppe fragt oder etwas für dich mitbringt.</p>
            </div>
        @else
            <ul class="card divide-y divide-line-soft overflow-hidden">
                @foreach ($notifications as $notification)
                    @php($unread = $unreadIds->contains($notification->id))
                    <li>
                        <a href="{{ route('trips.show', $notification->data['trip_id']) }}" class="flex items-start gap-3 px-5 py-4 hover:bg-line-soft">
                            <span class="mt-1.5 size-2.5 shrink-0 rounded-full {{ $unread ? 'bg-accent' : 'bg-transparent' }}" aria-hidden="true"></span>
                            <span class="min-w-0 flex-1">
                                <span class="block text-[15px] {{ $unread ? 'font-bold' : 'font-semibold' }}">{{ $notification->data['message'] }}</span>
                                <span class="mt-0.5 block text-xs font-semibold text-muted">{{ $notification->data['trip_name'] }} · {{ $notification->created_at->diffForHumans() }}</span>
                            </span>
                            <x-icon name="chevron-right" size="16" class="mt-1 text-muted" />
                        </a>
                    </li>
                @endforeach
            </ul>
            <p class="mt-3 text-center text-xs font-semibold text-muted">
                E-Mails zusätzlich? <a href="{{ route('profile.edit') }}" class="text-accent-ink underline">Im Profil einstellen</a>
            </p>
        @endif
    </div>
</x-layouts.app>
