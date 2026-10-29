{{-- Liste der Hinweise – auf der Seite und im Popup der Glocke ($compact) --}}
@if ($notifications->isEmpty())
    <div class="flex flex-col items-center px-6 {{ $compact ? 'py-8' : 'py-12' }} text-center">
        <span class="inline-flex size-14 items-center justify-center rounded-full bg-accent-soft text-accent-ink"><x-icon name="bell" size="26" /></span>
        <p class="mt-4 text-lg font-extrabold">Alles ruhig</p>
        <p class="mt-1 text-[15px] text-muted">Hier erscheint, wenn jemand in der Gruppe fragt oder etwas für dich mitbringt.</p>
    </div>
@else
    <ul class="divide-y divide-line-soft">
        @foreach ($notifications as $notification)
            @php($unread = $unreadIds->contains($notification->id))
            <li>
                <a href="{{ route('trips.show', $notification->data['trip_id']) }}" class="flex items-start gap-3 {{ $compact ? 'px-4 py-3' : 'px-5 py-4' }} hover:bg-line-soft">
                    <span class="mt-1.5 size-2.5 shrink-0 rounded-full {{ $unread ? 'bg-accent' : 'bg-transparent' }}" aria-hidden="true"></span>
                    <span class="min-w-0 flex-1">
                        <span class="block text-[15px] {{ $unread ? 'font-bold' : 'font-semibold' }}">{{ $notification->data['message'] }}</span>
                        <span class="mt-0.5 block text-xs font-semibold text-muted">{{ $notification->data['trip_name'] }} · {{ $notification->created_at->diffForHumans() }}</span>
                    </span>
                    @unless ($compact)<x-icon name="chevron-right" size="16" class="mt-1 text-muted" />@endunless
                </a>
            </li>
        @endforeach
    </ul>
@endif
