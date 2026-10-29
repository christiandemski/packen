@php use Illuminate\Support\Str; @endphp
<x-layouts.app title="Katalog">
    <div x-data="{ q: '', shown: [], added: 0, total: {{ $total }} }" x-on:quick-added="added++">
        <div class="mb-5">
            <p class="eyebrow" x-text="`${total + added} ${total + added === 1 ? 'Gegenstand' : 'Gegenstände'}`">{{ $total }} {{ $total === 1 ? 'Gegenstand' : 'Gegenstände' }}</p>
            <h1 class="page-title mt-1">Katalog</h1>
            <p class="mt-2 max-w-xl text-[15px] text-muted">Alles, was du jemals einpacken könntest. Aus dem Katalog baust du Vorlagen und Packlisten.</p>
            <div class="mt-3 flex flex-wrap gap-2">
                <a href="{{ route('categories.index') }}" class="btn btn-secondary min-h-10 text-sm"><x-icon name="grid" size="16" /> Kategorien</a>
                <a href="{{ route('containers.index') }}" class="btn btn-secondary min-h-10 text-sm"><x-icon name="luggage" size="16" /> Behältnisse</a>
            </div>
        </div>

        @if ($duplicates)
            <a href="{{ route('items.duplicates') }}" class="mb-5 flex items-center gap-3 rounded-card bg-accent-soft px-4 py-3 text-accent-ink hover:opacity-90">
                <x-icon name="layers" size="20" class="shrink-0" />
                <span class="min-w-0 flex-1 text-sm font-bold">{{ $duplicates === 1 ? '1 mögliche Doppelung' : $duplicates.' mögliche Doppelungen' }} im Katalog – aufräumen?</span>
                <x-icon name="chevron-right" size="16" />
            </a>
        @endif

        @if ($total === 0)
            <div class="card mb-5 flex flex-col items-center px-6 py-14 text-center" x-show="!added">
                <span class="inline-flex size-16 items-center justify-center rounded-full bg-accent-soft text-accent-ink"><x-icon name="package" size="30" /></span>
                <h2 class="mt-5 text-xl font-extrabold">Dein Katalog ist leer</h2>
                <p class="mt-2 max-w-sm text-[15px] text-muted">Leg Gegenstände einzeln an, tipp sie unten in eine Kategorie oder übernimm die Start-Vorlagen – die füllen den Katalog gleich mit.</p>
                <div class="mt-6 flex flex-wrap justify-center gap-2">
                    <a href="{{ route('items.create') }}" class="btn btn-primary"><x-icon name="plus" size="18" /> Gegenstand anlegen</a>
                    <a href="{{ route('templates.index') }}" class="btn btn-secondary"><x-icon name="sparkles" size="18" /> Start-Vorlagen</a>
                </div>
            </div>
        @else
            <div class="relative mb-5">
                <x-icon name="search" size="18" class="pointer-events-none absolute top-1/2 left-3.5 -translate-y-1/2 text-muted" />
                <input type="search" x-model="q" placeholder="Im Katalog suchen" aria-label="Im Katalog suchen" class="input pl-10">
            </div>
        @endif

        <div class="grid gap-4 lg:grid-cols-2 lg:items-start">
            @foreach ($sections as ['group' => $group, 'items' => $items, 'hidden' => $hidden])
                <section class="card overflow-hidden" x-data="quickAdd(@js(route('items.store')), @js($group->key))"
                         @if ($hidden) x-cloak @endif
                         x-on:catalog-reveal.window="$event.detail === category && $nextTick(() => { $el.scrollIntoView({ block: 'center', behavior: 'smooth' }); $refs.input.focus({ preventScroll: true }) })"
                         x-show="{{ $hidden ? 'shown.includes(category) && ' : '' }}(!q || added.length || @js($items->map(fn ($i) => Str::lower($i->name))->values()).some(n => n.includes(q.toLowerCase())))">
                    <div x-data="{ menu: false, picking: false, picked: [] }" @keydown.escape="menu = false; picking = false; picked = []">
                    <div class="flex items-center justify-between gap-2 pt-4 pr-2 pb-2 pl-5">
                        <h2 class="card-title flex min-w-0 items-center gap-2"><x-icon :name="$group->icon" size="18" class="text-accent" /> <span class="truncate">{{ $group->label }}</span></h2>
                        <div class="flex items-center gap-1">
                            <span class="text-xs font-bold text-muted" x-show="!picking" x-text="{{ $items->count() }} + added.length">{{ $items->count() }}</span>
                            <div class="relative" @click.outside="menu = false">
                                <button type="button" class="icon-btn size-9 min-h-9 min-w-9 text-muted" @click="menu = !menu" :aria-expanded="menu" aria-haspopup="true" aria-label="Mehr zu {{ $group->label }}"
                                        x-show="!picking && {{ $items->count() }} + added.length > 0"><x-icon name="ellipsis" size="18" /></button>
                                <div x-cloak x-show="menu" x-transition.origin.top.right class="absolute right-0 z-20 mt-1 w-60 overflow-hidden rounded-card border border-line bg-surface p-1.5 shadow-[0_20px_40px_-16px_rgba(23,33,30,0.35)]">
                                    <button type="button" class="flex w-full items-center gap-3 rounded-chip p-3 text-left text-[15px] font-bold hover:bg-line-soft" @click="menu = false; picking = true">
                                        <x-icon name="list-checks" size="18" class="text-accent" /> Mehrere verschieben
                                    </button>
                                </div>
                            </div>
                            <button type="button" x-cloak x-show="picking" class="text-sm font-bold text-accent-ink hover:underline px-2 min-h-9"
                                    @click="const all = [...$el.closest('section').querySelectorAll('[data-pick]')].map(c => +c.value); picked = picked.length === all.length ? [] : all"
                                    x-text="picked.length && picked.length === $el.closest('section').querySelectorAll('[data-pick]').length ? 'Keine' : 'Alle'"></button>
                        </div>
                    </div>
                    <ul class="divide-y divide-line-soft border-b border-line-soft">
                        @foreach ($items as $item)
                            <li x-show="!q || @js(Str::lower($item->name)).includes(q.toLowerCase())">
                                <label x-cloak x-show="picking" class="flex min-h-[52px] cursor-pointer items-center gap-3 px-5 py-2.5 hover:bg-line-soft">
                                    <input type="checkbox" data-pick value="{{ $item->id }}" x-model.number="picked" class="size-5 shrink-0 accent-[var(--accent)]">
                                    <span class="min-w-0 flex-1 truncate text-[15px] font-semibold">{{ $item->name }}</span>
                                </label>
                                <a x-show="!picking" href="{{ route('items.edit', $item) }}" class="flex min-h-[52px] items-center gap-3 px-5 py-2.5 hover:bg-line-soft">
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-[15px] font-semibold">{{ $item->name }}</p>
                                        @if ($item->per_day || $item->pack_last || $item->weatherNeeds() || $item->templates_count)
                                            <p class="mt-0.5 flex flex-wrap gap-x-3 text-xs font-bold text-muted">
                                                @if ($item->per_day)<span class="text-accent-ink">pro Tag</span>@endif
                                                @if ($item->pack_last)<span>Am Abfahrtstag</span>@endif
                                                @foreach ($item->weatherNeeds() as $need)<span class="inline-flex items-center gap-1"><x-icon :name="$need->badgeIcon()" size="12" /> {{ $need->badge() }}</span>@endforeach
                                                @if ($item->templates_count)<span>in {{ $item->templates_count }} {{ $item->templates_count === 1 ? 'Vorlage' : 'Vorlagen' }}</span>@endif
                                            </p>
                                        @endif
                                    </div>
                                    @if ($item->weightLabel())
                                        <span class="text-[13px] font-semibold text-muted">{{ $item->weightLabel() }}</span>
                                    @endif
                                    <x-icon name="chevron-right" size="16" class="text-muted" />
                                </a>
                            </li>
                        @endforeach
                        <template x-for="item in added" :key="item.id">
                            <li>
                                <label x-show="picking" class="flex min-h-[52px] cursor-pointer items-center gap-3 px-5 py-2.5 hover:bg-line-soft">
                                    <input type="checkbox" data-pick :value="item.id" x-model.number="picked" class="size-5 shrink-0 accent-[var(--accent)]">
                                    <span class="min-w-0 flex-1 truncate text-[15px] font-semibold" x-text="item.name"></span>
                                </label>
                                <a x-show="!picking" :href="item.url" class="flex min-h-[52px] items-center gap-3 px-5 py-2.5 hover:bg-line-soft">
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-[15px] font-semibold" x-text="item.name"></p>
                                        <p class="mt-0.5 flex flex-wrap gap-x-3 text-xs font-bold text-muted">
                                            <span class="text-accent-ink">neu</span>
                                            <template x-for="badge in item.badges"><span x-text="badge"></span></template>
                                        </p>
                                    </div>
                                    <x-icon name="chevron-right" size="16" class="text-muted" />
                                </a>
                            </li>
                        </template>
                    </ul>
                    <form x-cloak x-show="picking" method="POST" action="{{ route('items.move') }}" class="space-y-3 bg-line-soft/60 px-5 py-4">
                        @csrf
                        <template x-for="id in picked" :key="id"><input type="hidden" name="items[]" :value="id"></template>
                        <div>
                            <label for="move-{{ $group->key }}" class="label" x-text="picked.length ? `${picked.length} ${picked.length === 1 ? 'Gegenstand' : 'Gegenstände'} verschieben nach` : 'Gegenstände antippen, dann Ziel wählen'"></label>
                            <select id="move-{{ $group->key }}" name="category" required class="input">
                                @foreach ($targets->where('key', '!=', $group->key) as $target)
                                    <option value="{{ $target->key }}">{{ $target->label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <button type="submit" class="btn btn-primary" :disabled="!picked.length" :class="!picked.length && 'opacity-50'">Verschieben</button>
                            <button type="button" class="btn btn-secondary" @click="picking = false; picked = []">Abbrechen</button>
                        </div>
                    </form>
                    <div x-show="!picking"><x-quick-add :label="$group->label" /></div>
                    </div>
                </section>
            @endforeach
        </div>

        @php($empty = $sections->where('hidden', true))
        @if ($empty->isNotEmpty())
            <div class="mt-6" x-show="!q && shown.length < {{ $empty->count() }}">
                <p class="mb-2 text-xs font-bold tracking-[0.06em] text-muted uppercase">Weitere Kategorie füllen</p>
                <div class="flex flex-wrap gap-2">
                    @foreach ($empty as ['group' => $group])
                        <button type="button" class="chip border border-dashed border-muted/60 text-muted hover:border-ink hover:text-ink"
                                x-show="!shown.includes(@js($group->key))" @click="shown.push(@js($group->key)); $dispatch('catalog-reveal', @js($group->key))">
                            <x-icon :name="$group->icon" size="14" /> {{ $group->label }}
                        </button>
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    @if ($total > 0)
        <div class="fixed right-4 bottom-20 z-20 sm:bottom-6" x-data="{ open: false }" @keydown.escape.window="open = false" @click.outside="open = false">
            <div x-cloak x-show="open" x-transition.origin.bottom.right class="absolute right-0 bottom-full mb-2 w-64 overflow-hidden rounded-card border border-line bg-surface p-1.5 shadow-[0_20px_40px_-16px_rgba(23,33,30,0.35)]">
                <a href="{{ route('items.create') }}" class="flex items-center gap-3 rounded-chip p-3 hover:bg-line-soft">
                    <span class="inline-flex size-10 items-center justify-center rounded-chip bg-accent-soft text-accent-ink"><x-icon name="package" /></span>
                    <span><span class="block text-[15px] font-bold">Gegenstand</span><span class="block text-xs font-semibold text-muted">Mit allen Angaben anlegen</span></span>
                </a>
                <a href="{{ route('categories.index', ['neu' => 1]) }}#neu" class="flex items-center gap-3 rounded-chip p-3 hover:bg-line-soft">
                    <span class="inline-flex size-10 items-center justify-center rounded-chip bg-line-soft"><x-icon name="grid" /></span>
                    <span><span class="block text-[15px] font-bold">Kategorie</span><span class="block text-xs font-semibold text-muted">Eigene, z. B. „Kamera“</span></span>
                </a>
                <a href="{{ route('containers.index', ['neu' => 1]) }}#neu" class="flex items-center gap-3 rounded-chip p-3 hover:bg-line-soft">
                    <span class="inline-flex size-10 items-center justify-center rounded-chip bg-line-soft"><x-icon name="luggage" /></span>
                    <span><span class="block text-[15px] font-bold">Behältnis</span><span class="block text-xs font-semibold text-muted">Koffer, Rucksack, Tasche …</span></span>
                </a>
            </div>
            <button type="button" class="btn btn-primary shadow-[0_12px_28px_-10px_rgba(14,107,94,0.6)]" @click="open = !open" :aria-expanded="open" aria-haspopup="true">
                <x-icon name="plus" size="18" class="transition-transform" ::class="open && 'rotate-45'" /> Neu
            </button>
        </div>
    @endif
</x-layouts.app>
