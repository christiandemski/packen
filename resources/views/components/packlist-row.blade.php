@props(['trip', 'entry', 'containers', 'view' => 'groups', 'canEdit' => true])
@php
    use App\Enums\EntrySource;

    $toggleUrl = route('packlist.toggle', [$trip, $entry]);
    $perDayHint = $entry->item?->per_day && ! $entry->quantity_locked && $entry->source === EntrySource::Template;
    $containerName = $view === 'groups' ? $containers->firstWhere('id', $entry->container_id)?->name : null;
    $details = array_filter([$entry->note, $perDayHint ? $trip->days().' Tage' : null, $containerName ? 'in: '.$containerName : null, $entry->packed && $entry->packer ? 'gepackt von '.$entry->packer->name : null]);
    $uid = $view.'-'.$entry->id;
    $id = $entry->id;
@endphp
<li x-data="{ edit: false }" x-show="removed[{{ $id }}] ? editing : ({{ $id }} in current && (!onlyOpen || !current[{{ $id }}]))">
    @if ($canEdit)
        {{-- Im Anpassen-Modus entfernt: kurz stehen lassen, damit man es zurückholen kann --}}
        <div x-show="removed[{{ $id }}]" x-cloak class="flex min-h-[52px] items-center gap-3 bg-line-soft/60 py-2 pr-3 pl-5">
            <span class="min-w-0 flex-1 truncate text-[15px] font-semibold text-muted line-through">{{ $entry->name() }}</span>
            <button type="button" x-show="removed[{{ $id }}]?.restorable" @click="restore({{ $id }}, @js(route('packlist.restore', [$trip, $entry])))"
                    class="min-h-11 px-2 text-sm font-bold text-accent-ink hover:underline">Doch mitnehmen</button>
            <span x-show="removed[{{ $id }}] && !removed[{{ $id }}].restorable && !removed[{{ $id }}].pending" class="text-xs font-bold text-muted">entfernt</span>
        </div>
    @endif
    <div class="flex min-h-[52px] items-center" x-show="!removed[{{ $id }}]">
        <button type="button" role="checkbox" :aria-checked="!!current[{{ $entry->id }}]" aria-checked="{{ $entry->packed ? 'true' : 'false' }}"
                @click="toggle({{ $entry->id }}, @js($toggleUrl), @js(route('packlist.return', [$trip, $entry])))"
                class="flex min-w-0 flex-1 items-center gap-3 py-2.5 pl-5 text-left">
            <span class="inline-flex size-6 shrink-0 items-center justify-center rounded-[7px] border-2 transition"
                  :class="current[{{ $entry->id }}] ? 'border-accent bg-accent text-white' : 'border-line bg-surface text-transparent'">
                <x-icon name="check" size="16" :stroke-width="3" />
            </span>
            <span class="min-w-0">
                <span class="block truncate text-[15px] font-semibold transition" :class="current[{{ $entry->id }}] && 'text-muted line-through'">{{ $entry->name() }}</span>
                @if ($details)
                    <span class="block truncate text-xs font-semibold text-muted">{{ implode(' · ', $details) }}</span>
                @endif
            </span>
        </button>
        @if ($view === 'containers' && $entry->weight() > 0)
            <span class="text-xs font-semibold text-muted">{{ \App\Models\Container::kg($entry->weight()) }}</span>
        @endif
        @if ($canEdit)
        <span x-show="editing" x-cloak class="ml-2 rounded-full bg-line-soft px-2.5 py-1 text-[13px] font-extrabold">{{ $entry->quantity }}×</span>
        <button type="button" x-show="editing" x-cloak @click="remove({{ $id }}, @js(route('packlist.destroy', [$trip, $entry])))"
                class="mr-1 ml-2 inline-flex min-h-11 min-w-11 items-center justify-center rounded-chip text-muted hover:bg-warn/10 hover:text-warn-ink"
                aria-label="{{ $entry->name() }} für diese Reise weglassen" title="Für diese Reise weglassen">
            <x-icon name="x" size="20" />
        </button>
        <button type="button" x-show="!editing" @click="edit = !edit" :aria-expanded="edit" class="mr-3 ml-2 inline-flex min-h-11 min-w-11 items-center justify-center rounded-chip px-2" aria-label="{{ $entry->name() }} bearbeiten">
            <span class="rounded-full bg-line-soft px-2.5 py-1 text-[13px] font-extrabold {{ $entry->quantity_locked ? 'text-accent-ink' : 'text-ink' }}">{{ $entry->quantity }}×</span>
        </button>
        @else
            <span class="mr-5 ml-2 rounded-full bg-line-soft px-2.5 py-1 text-[13px] font-extrabold">{{ $entry->quantity }}×</span>
        @endif
    </div>
    @if ($canEdit)
    <div x-show="edit && !editing && !removed[{{ $id }}]" x-cloak class="flex flex-wrap items-end gap-2 bg-line-soft/60 px-5 py-3">
        <form method="POST" action="{{ route('packlist.update', [$trip, $entry]) }}" data-keep-scroll class="flex flex-1 flex-wrap items-end gap-2">
            @csrf @method('PATCH')
            <input type="hidden" name="ansicht" value="{{ $view === 'containers' ? 'behaeltnisse' : '' }}">
            <div class="w-24">
                <label class="label" for="qty-{{ $uid }}">Menge</label>
                <input id="qty-{{ $uid }}" name="quantity" type="number" min="1" max="999" inputmode="numeric" value="{{ $entry->quantity }}" class="input">
            </div>
            <div class="min-w-40 flex-1">
                <label class="label" for="note-{{ $uid }}">Notiz</label>
                <input id="note-{{ $uid }}" name="note" maxlength="200" value="{{ $entry->note }}" class="input">
            </div>
            @if ($containers->isNotEmpty())
                <div class="min-w-40 flex-1">
                    <label class="label" for="container-{{ $uid }}">Behältnis</label>
                    <select id="container-{{ $uid }}" name="container_id" class="input">
                        <option value="">Ohne Behältnis</option>
                        @foreach ($containers as $container)
                            <option value="{{ $container->id }}" @selected($entry->container_id === $container->id)>{{ $container->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <button type="submit" class="btn btn-primary">Speichern</button>
        </form>
        <form method="POST" action="{{ route('packlist.destroy', [$trip, $entry]) }}" data-keep-scroll>
            @csrf @method('DELETE')
            <button type="submit" class="icon-btn text-warn-ink" aria-label="{{ $entry->name() }} entfernen"><x-icon name="trash" size="18" /></button>
        </form>
        @if ($trip->isGroup() && ! $entry->group_item_id)
            <form method="POST" action="{{ route('group-items.store', $trip) }}" class="w-full">
                @csrf
                <input type="hidden" name="kind" value="request">
                <input type="hidden" name="name" value="{{ $entry->name() }}">
                <input type="hidden" name="source_entry_id" value="{{ $entry->id }}">
                <button type="submit" class="text-sm font-bold text-accent-ink hover:underline"><x-icon name="users" size="14" class="inline" /> Hab ich nicht – in die Gruppe fragen</button>
            </form>
        @endif
    </div>
    @endif
</li>
