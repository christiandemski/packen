@php use App\Enums\ItemCategory; use Illuminate\Support\Str; @endphp
<x-layouts.app title="Katalog">
    <div x-data="{ q: '' }">
        <div class="mb-5">
            <p class="eyebrow">{{ $total }} {{ $total === 1 ? 'Gegenstand' : 'Gegenstände' }}</p>
            <h1 class="page-title mt-1">Katalog</h1>
            <p class="mt-2 max-w-xl text-[15px] text-muted">Alles, was du jemals einpacken könntest. Aus dem Katalog baust du Vorlagen und Packlisten.</p>
            <a href="{{ route('containers.index') }}" class="btn btn-secondary mt-3 min-h-10 text-sm"><x-icon name="luggage" size="16" /> Behältnisse</a>
        </div>

        @if ($total > 0)
            <div class="relative mb-5">
                <x-icon name="search" size="18" class="pointer-events-none absolute top-1/2 left-3.5 -translate-y-1/2 text-muted" />
                <input type="search" x-model="q" placeholder="Im Katalog suchen" aria-label="Im Katalog suchen" class="input pl-10">
            </div>

            <div class="grid gap-4 lg:grid-cols-2 lg:items-start">
                @foreach ($groups as $category => $items)
                    @php($cat = ItemCategory::from($category))
                    <section class="card overflow-hidden" x-show="!q || @js($items->map(fn ($i) => Str::lower($i->name))->values()).some(n => n.includes(q.toLowerCase()))">
                        <div class="flex items-center justify-between px-5 pt-4 pb-2">
                            <h2 class="card-title flex items-center gap-2"><x-icon :name="$cat->icon()" size="18" class="text-accent" /> {{ $cat->label() }}</h2>
                            <span class="text-xs font-bold text-muted">{{ $items->count() }}</span>
                        </div>
                        <ul class="divide-y divide-line-soft">
                            @foreach ($items as $item)
                                <li x-show="!q || @js(Str::lower($item->name)).includes(q.toLowerCase())">
                                    <a href="{{ route('items.edit', $item) }}" class="flex min-h-[52px] items-center gap-3 px-5 py-2.5 hover:bg-line-soft">
                                        <div class="min-w-0 flex-1">
                                            <p class="truncate text-[15px] font-semibold">{{ $item->name }}</p>
                                            @if ($item->per_day || $item->pack_last || $item->rain_protection || $item->templates_count)
                                                <p class="mt-0.5 flex flex-wrap gap-x-3 text-xs font-bold text-muted">
                                                    @if ($item->per_day)<span class="text-accent-ink">pro Tag</span>@endif
                                                    @if ($item->pack_last)<span>Am Abfahrtstag</span>@endif
                                                    @if ($item->rain_protection)<span class="inline-flex items-center gap-1"><x-icon name="umbrella" size="12" /> Regenschutz</span>@endif
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
                        </ul>
                    </section>
                @endforeach
            </div>
        @else
            <div class="card flex flex-col items-center px-6 py-14 text-center">
                <span class="inline-flex size-16 items-center justify-center rounded-full bg-accent-soft text-accent-ink"><x-icon name="package" size="30" /></span>
                <h2 class="mt-5 text-xl font-extrabold">Dein Katalog ist leer</h2>
                <p class="mt-2 max-w-sm text-[15px] text-muted">Leg Gegenstände einzeln an oder übernimm die Start-Vorlagen – die füllen den Katalog gleich mit.</p>
                <div class="mt-6 flex flex-wrap justify-center gap-2">
                    <a href="{{ route('items.create') }}" class="btn btn-primary"><x-icon name="plus" size="18" /> Gegenstand anlegen</a>
                    <a href="{{ route('templates.index') }}" class="btn btn-secondary"><x-icon name="sparkles" size="18" /> Start-Vorlagen</a>
                </div>
            </div>
        @endif
    </div>

    @if ($total > 0)
        <a href="{{ route('items.create') }}" class="btn btn-primary fixed right-4 bottom-20 z-20 shadow-[0_12px_28px_-10px_rgba(14,107,94,0.6)] sm:bottom-6">
            <x-icon name="plus" size="18" /> Gegenstand
        </a>
    @endif
</x-layouts.app>
