{{-- „Offene Anfragen“ anderer Mitreisender – oben in der eigenen Packliste --}}
@php($openRequests = $groupItems->filter(fn ($g) => $g->isRequest() && $g->isOpen() && $g->requested_by !== auth()->id()))
@if ($canEdit && $openRequests->isNotEmpty())
    <section class="card mb-4 border-accent/40 bg-accent-soft/40 p-5" aria-labelledby="open-requests">
        <h2 id="open-requests" class="card-title flex items-center gap-2">
            <x-icon name="users" size="18" class="text-accent" /> Offene Anfragen
            <span class="chip bg-accent text-white">{{ $openRequests->count() }}</span>
        </h2>
        <ul class="mt-3 space-y-2">
            @foreach ($openRequests as $request)
                <li class="flex flex-wrap items-center gap-3 rounded-chip bg-surface p-3">
                    <x-avatar :user="$request->requester" size="sm" />
                    <div class="min-w-0 flex-1">
                        <p class="text-[15px] font-bold">{{ $request->name }}</p>
                        <p class="text-xs font-semibold text-muted">{{ $request->requesterName() }} fragt{{ $request->note ? ' · '.$request->note : '' }}</p>
                    </div>
                    <form method="POST" action="{{ route('group-items.claim', [$trip, $request]) }}">
                        @csrf
                        <button type="submit" class="btn btn-primary min-h-10 text-sm"><x-icon name="check" size="16" /> Annehmen</button>
                    </form>
                </li>
            @endforeach
        </ul>
    </section>
@endif
