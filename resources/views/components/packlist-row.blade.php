@props(['trip', 'entry', 'containers', 'view' => 'groups'])
@php
    use App\Enums\EntrySource;

    $toggleUrl = route('packlist.toggle', [$trip, $entry]);
    $perDayHint = $entry->item?->per_day && ! $entry->quantity_locked && $entry->source === EntrySource::Template;
    $containerName = $view === 'groups' ? $containers->firstWhere('id', $entry->container_id)?->name : null;
    $details = array_filter([$entry->note, $perDayHint ? $trip->days().' Tage' : null, $containerName ? 'in: '.$containerName : null]);
    $uid = $view.'-'.$entry->id;
@endphp
<li x-data="{ edit: false }" x-show="!onlyOpen || !packed[{{ $entry->id }}]">
    <div class="flex min-h-[52px] items-center">
        <button type="button" role="checkbox" :aria-checked="!!packed[{{ $entry->id }}]" aria-checked="{{ $entry->packed ? 'true' : 'false' }}"
                @click="toggle({{ $entry->id }}, @js($toggleUrl))"
                class="flex min-w-0 flex-1 items-center gap-3 py-2.5 pl-5 text-left">
            <span class="inline-flex size-6 shrink-0 items-center justify-center rounded-[7px] border-2 transition"
                  :class="packed[{{ $entry->id }}] ? 'border-accent bg-accent text-white' : 'border-line bg-surface text-transparent'">
                <x-icon name="check" size="16" :stroke-width="3" />
            </span>
            <span class="min-w-0">
                <span class="block truncate text-[15px] font-semibold transition" :class="packed[{{ $entry->id }}] && 'text-muted line-through'">{{ $entry->name() }}</span>
                @if ($details)
                    <span class="block truncate text-xs font-semibold text-muted">{{ implode(' · ', $details) }}</span>
                @endif
            </span>
        </button>
        @if ($view === 'containers' && $entry->weight() > 0)
            <span class="text-xs font-semibold text-muted">{{ \App\Models\Container::kg($entry->weight()) }}</span>
        @endif
        <button type="button" @click="edit = !edit" :aria-expanded="edit" class="mr-3 ml-2 inline-flex min-h-11 min-w-11 items-center justify-center rounded-chip px-2" aria-label="{{ $entry->name() }} bearbeiten">
            <span class="rounded-full bg-line-soft px-2.5 py-1 text-[13px] font-extrabold {{ $entry->quantity_locked ? 'text-accent-ink' : 'text-ink' }}">{{ $entry->quantity }}×</span>
        </button>
    </div>
    <div x-show="edit" x-cloak class="flex flex-wrap items-end gap-2 bg-line-soft/60 px-5 py-3">
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
    </div>
</li>
