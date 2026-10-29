@php
    use App\Enums\PacklistGroup;
    use App\Http\Controllers\ReviewController as R;
    use App\Models\Container;

    $all = $groups->flatten(1);
    $unused = $all->where('review', R::UNUSED);
    $missing = $all->where('review', R::MISSING);
    $unusedWeight = $unused->sum(fn ($e) => $e->weight());
    $status = $trip->status();
@endphp
<div class="space-y-4">
    @if ($status !== 'past')
        <p class="rounded-chip bg-line-soft px-4 py-3 text-sm font-semibold text-muted">
            Der Rückblick ist für nach der Reise gedacht – du kannst aber jederzeit schon Notizen machen.
        </p>
    @endif

    {{-- Auswertung --}}
    <section class="card p-5">
        <p class="card-title">Auswertung</p>
        <div class="mt-3 grid grid-cols-3 gap-2 text-center">
            <div class="rounded-chip bg-line-soft p-3">
                <p class="text-2xl font-extrabold">{{ $all->count() }}</p>
                <p class="text-xs font-bold text-muted">auf der Liste</p>
            </div>
            <div class="rounded-chip {{ $unused->isNotEmpty() ? 'bg-warn-soft text-warn-ink' : 'bg-line-soft' }} p-3">
                <p class="text-2xl font-extrabold">{{ $unused->count() }}</p>
                <p class="text-xs font-bold {{ $unused->isNotEmpty() ? '' : 'text-muted' }}">nicht gebraucht</p>
            </div>
            <div class="rounded-chip {{ $missing->isNotEmpty() ? 'bg-accent-soft text-accent-ink' : 'bg-line-soft' }} p-3">
                <p class="text-2xl font-extrabold">{{ $missing->count() }}</p>
                <p class="text-xs font-bold {{ $missing->isNotEmpty() ? '' : 'text-muted' }}">hat gefehlt</p>
            </div>
        </div>
        @if ($all->isNotEmpty() && ($unused->isNotEmpty() || $missing->isNotEmpty()))
            <p class="mt-3 text-sm font-semibold text-muted">
                @if ($unused->isNotEmpty())
                    {{ round($unused->count() / $all->count() * 100) }} % umsonst mitgenommen{{ $unusedWeight > 0 ? ' – '.Container::kg($unusedWeight).' Ballast' : '' }}.
                @endif
                @if ($missing->isNotEmpty())
                    {{ $missing->count() === 1 ? 'Ein Ding hat' : $missing->count().' Dinge haben' }} gefehlt – übernimm {{ $missing->count() === 1 ? 'es' : 'sie' }} unten in eine Vorlage.
                @endif
            </p>
        @endif
    </section>

    {{-- Hat gefehlt --}}
    <section class="card p-5">
        <p class="card-title">Was hat gefehlt?</p>
        <form method="POST" action="{{ route('review.missing', $trip) }}" data-keep-scroll class="mt-3 flex gap-2">
            @csrf
            <input name="name" required maxlength="120" placeholder="z. B. Regenschirm" aria-label="Was hat gefehlt" class="input flex-1">
            <button type="submit" class="btn btn-secondary"><x-icon name="plus" size="18" /> Notieren</button>
        </form>
        @error('name')<p class="field-error">{{ $message }}</p>@enderror

        @if ($missing->isNotEmpty())
            <ul class="mt-4 divide-y divide-line-soft">
                @foreach ($missing as $entry)
                    <li class="flex flex-wrap items-center gap-2 py-2.5">
                        <span class="min-w-0 flex-1 text-[15px] font-semibold">{{ $entry->name() }}</span>
                        @if ($templates->isNotEmpty())
                            <form method="POST" action="{{ route('review.template.add', [$trip, $entry]) }}" data-keep-scroll class="flex gap-1">
                                @csrf
                                <select name="template_id" aria-label="Vorlage für {{ $entry->name() }}" class="input min-h-10 py-0 text-sm">
                                    @foreach ($templates as $template)
                                        <option value="{{ $template->id }}" @selected($selected->contains($template->id) && $loop->first)>{{ $template->name }}</option>
                                    @endforeach
                                </select>
                                <button type="submit" class="btn btn-primary min-h-10 px-3 text-sm">In Vorlage</button>
                            </form>
                        @endif
                        <form method="POST" action="{{ route('review.update', [$trip, $entry]) }}" data-keep-scroll>
                            @csrf @method('PATCH')
                            <button type="submit" class="icon-btn text-muted" aria-label="Markierung für {{ $entry->name() }} entfernen"><x-icon name="x" size="16" /></button>
                        </form>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    {{-- Pro Eintrag markieren --}}
    @foreach ($groups as $entries)
        @php($group = $entries->first()->packlistGroup())
        @php($unusedLabel = $group->is(PacklistGroup::Clothing) ? 'nicht getragen' : 'nicht gebraucht')
        <section class="card overflow-hidden">
            <h2 class="card-title flex items-center gap-2 px-5 pt-4 pb-2"><x-icon :name="$group->icon" size="18" class="text-accent" /> {{ $group->label }}</h2>
            <ul class="divide-y divide-line-soft">
                @foreach ($entries as $entry)
                    <li class="px-5 py-2.5">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="min-w-0 flex-1 text-[15px] font-semibold {{ $entry->review === R::UNUSED ? 'text-muted line-through' : '' }}">
                                {{ $entry->name() }} <span class="text-xs text-muted">{{ $entry->quantity }}×</span>
                            </span>
                            @foreach ([R::UNUSED => $unusedLabel, R::MISSING => 'hat gefehlt'] as $value => $label)
                                <form method="POST" action="{{ route('review.update', [$trip, $entry]) }}" data-keep-scroll>
                                    @csrf @method('PATCH')
                                    <input type="hidden" name="review" value="{{ $entry->review === $value ? '' : $value }}">
                                    <button type="submit" aria-pressed="{{ $entry->review === $value ? 'true' : 'false' }}"
                                            class="chip min-h-9 {{ $entry->review === $value ? ($value === R::UNUSED ? 'bg-warn text-white' : 'bg-accent text-white') : 'bg-line-soft text-muted hover:text-ink' }}">
                                        {{ $label }}
                                    </button>
                                </form>
                            @endforeach
                        </div>
                        @if ($entry->review === R::UNUSED && $entry->item)
                            @php($inTemplates = $entry->item->templates)
                            @if ($inTemplates->isNotEmpty())
                                <div class="mt-2 flex flex-wrap items-center gap-1.5 text-xs font-semibold text-muted">
                                    Steckt in:
                                    @foreach ($inTemplates as $template)
                                        <form method="POST" action="{{ route('review.template.remove', [$trip, $entry, $template]) }}" data-keep-scroll>
                                            @csrf @method('DELETE')
                                            <button type="submit" class="chip bg-warn-soft text-warn-ink hover:bg-warn hover:text-white" title="Aus der Vorlage streichen">
                                                {{ $template->name }} <x-icon name="x" size="12" />
                                            </button>
                                        </form>
                                    @endforeach
                                </div>
                            @endif
                        @endif
                    </li>
                @endforeach
            </ul>
        </section>
    @endforeach
</div>
