{{-- Karte „Gemeinsam“: Dinge, die nur eine Person mitbringen muss, und alle Anfragen --}}
@php($me = auth()->user())
<section class="card mt-4 overflow-hidden" aria-labelledby="group-items">
    <div class="flex flex-wrap items-center justify-between gap-3 px-5 pt-4 pb-2">
        <h2 id="group-items" class="card-title flex items-center gap-2"><x-icon name="users" size="18" class="text-accent" /> Gemeinsam</h2>
        <button type="button" class="btn btn-secondary min-h-10 text-sm" @click="$dispatch('open-sheet', 'group-item')"><x-icon name="plus" size="16" /> Gemeinsam / Anfrage</button>
    </div>
    @if ($groupItems->isEmpty())
        <p class="px-5 pb-4 text-sm text-muted">Adapter, Sonnencreme, Zelt – was nur eine Person mitbringen muss. Oder frag die Gruppe, ob jemand etwas für dich mitnimmt.</p>
    @else
        <ul class="divide-y divide-line-soft">
            @foreach ($groupItems as $groupItem)
                @php($mine = $groupItem->assigned_user_id === $me->id)
                <li class="flex flex-wrap items-center gap-3 px-5 py-3">
                    @if ($groupItem->assignee)
                        <x-avatar :user="$groupItem->assignee" size="sm" />
                    @else
                        <span class="inline-flex size-7 items-center justify-center rounded-full border-2 border-dashed border-muted/50 text-muted"><x-icon name="plus" size="12" /></span>
                    @endif
                    <div class="min-w-0 flex-1">
                        <p class="text-[15px] font-bold">{{ $groupItem->name }}</p>
                        <p class="text-xs font-semibold {{ $groupItem->isOpen() ? 'text-warn-ink' : 'text-muted' }}">
                            @if ($groupItem->isRequest())
                                {{ $groupItem->requested_by === $me->id ? 'Deine Anfrage' : $groupItem->requesterName().' fragt' }} ·
                            @endif
                            @if ($groupItem->isOpen())
                                noch offen
                            @else
                                {{ $mine ? 'Bringst du mit' : 'Bringt '.($groupItem->assignee?->name ?? 'Ehemaliges Mitglied').' mit' }}
                            @endif
                            @if ($groupItem->note) · {{ $groupItem->note }} @endif
                        </p>
                    </div>
                    <div class="flex gap-1">
                        @if ($groupItem->isOpen() && ! ($groupItem->isRequest() && $groupItem->requested_by === $me->id))
                            <form method="POST" action="{{ route('group-items.claim', [$trip, $groupItem]) }}">
                                @csrf
                                <button type="submit" class="btn btn-ghost min-h-10 px-3 text-sm">Ich bringe es mit</button>
                            </form>
                        @endif
                        @if ($mine)
                            <form method="POST" action="{{ route('group-items.release', [$trip, $groupItem]) }}">
                                @csrf
                                <button type="submit" class="btn btn-ghost min-h-10 px-3 text-sm">Abgeben</button>
                            </form>
                        @endif
                        @if (($groupItem->isRequest() && $groupItem->requested_by === $me->id) || (! $groupItem->isRequest() && ($groupItem->created_by === $me->id || $trip->isOwnedBy($me))))
                            <form method="POST" action="{{ route('group-items.withdraw', [$trip, $groupItem]) }}">
                                @csrf
                                <button type="submit" class="icon-btn text-muted hover:text-warn-ink" aria-label="{{ $groupItem->isRequest() ? 'Anfrage zurückziehen' : 'Streichen' }}" title="{{ $groupItem->isRequest() ? 'Anfrage zurückziehen' : 'Streichen' }}"><x-icon name="x" size="18" /></button>
                            </form>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</section>

<x-sheet name="group-item" title="Gemeinsam">
    <form method="POST" action="{{ route('group-items.store', $trip) }}" x-data="{ kind: 'request' }" class="space-y-3">
        @csrf
        <div class="segment" role="radiogroup" aria-label="Art">
            <label class="segment-item cursor-pointer has-checked:bg-surface has-checked:text-ink has-checked:shadow-sm">
                <input type="radio" name="kind" value="request" x-model="kind" class="sr-only"> In die Gruppe fragen
            </label>
            <label class="segment-item cursor-pointer has-checked:bg-surface has-checked:text-ink has-checked:shadow-sm">
                <input type="radio" name="kind" value="planned" x-model="kind" class="sr-only"> Gemeinsam planen
            </label>
        </div>
        <p class="text-sm text-muted" x-show="kind === 'request'">Die anderen sehen deine Anfrage oben in ihrer Packliste. Wer sie annimmt, hat den Gegenstand auf seiner Liste.</p>
        <p class="text-sm text-muted" x-show="kind === 'planned'" x-cloak>Etwas, das nur eine Person mitbringen muss – gleich zuweisen oder offen lassen.</p>
        <div>
            <label for="gi-name" class="label">Gegenstand</label>
            <input id="gi-name" name="name" required maxlength="120" autofocus placeholder="z. B. Föhn" class="input">
        </div>
        <div>
            <label for="gi-note" class="label">Notiz <span class="normal-case tracking-normal">(optional)</span></label>
            <input id="gi-note" name="note" maxlength="200" placeholder="z. B. USB-C reicht" class="input">
        </div>
        <div x-show="kind === 'planned'" x-cloak>
            <label for="gi-assignee" class="label">Wer bringt es mit?</label>
            <select id="gi-assignee" name="assigned_user_id" class="input">
                <option value="">Noch offen</option>
                @foreach ($trip->members as $member)
                    <option value="{{ $member->id }}">{{ $member->is($me) ? 'Ich' : $member->name }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="btn btn-primary w-full" x-text="kind === 'request' ? 'Anfrage schicken' : 'Anlegen'">Anfrage schicken</button>
    </form>
</x-sheet>
