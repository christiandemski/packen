<x-layouts.app title="Reisen">
    <div x-data="tripOverview">
        <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="eyebrow">Hallo {{ auth()->user()->name }}</p>
                <h1 class="page-title mt-1">Deine Reisen</h1>
            </div>

            <div class="flex items-center gap-2">
                @if ($upcoming->isNotEmpty() || $past->isNotEmpty())
                    <div class="segment" role="group" aria-label="Ansicht">
                        <button type="button" class="segment-item" :aria-pressed="view === 'tiles'" @click="setView('tiles')" aria-label="Kacheln">
                            <x-icon name="grid" size="18" />
                        </button>
                        <button type="button" class="segment-item" :aria-pressed="view === 'list'" @click="setView('list')" aria-label="Liste">
                            <x-icon name="list" size="18" />
                        </button>
                    </div>
                @endif

                <div class="relative" x-data="{ open: false }" @keydown.escape.window="open = false" @click.outside="open = false">
                    <button type="button" class="btn btn-primary" @click="open = !open" :aria-expanded="open" aria-haspopup="true">
                        <x-icon name="plus" size="18" /> Neue Liste
                    </button>
                    <div x-cloak x-show="open" x-transition.origin.top.right class="absolute right-0 z-20 mt-2 w-64 overflow-hidden rounded-card border border-line bg-surface p-1.5 shadow-[0_20px_40px_-16px_rgba(23,33,30,0.35)]">
                        <a href="{{ route('trips.create', ['mode' => 'solo']) }}" class="flex items-center gap-3 rounded-chip p-3 hover:bg-line-soft">
                            <span class="inline-flex size-10 items-center justify-center rounded-chip bg-line-soft"><x-icon name="user" /></span>
                            <span><span class="block text-[15px] font-bold">Für mich</span><span class="block text-xs font-semibold text-muted">Private Packliste</span></span>
                        </a>
                        <a href="{{ route('trips.create', ['mode' => 'group']) }}" class="flex items-center gap-3 rounded-chip p-3 hover:bg-line-soft">
                            <span class="inline-flex size-10 items-center justify-center rounded-chip bg-accent-soft text-accent-ink"><x-icon name="users" /></span>
                            <span><span class="block text-[15px] font-bold">Für eine Gruppe</span><span class="block text-xs font-semibold text-muted">Gemeinsame Reise mit Freunden</span></span>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        @if (auth()->user()->starter_templates_at === null)
            <div class="card mb-6 flex flex-col gap-4 border-accent/40 bg-accent-soft/40 p-5 sm:flex-row sm:items-center">
                <span class="inline-flex size-10 shrink-0 items-center justify-center rounded-chip bg-accent text-white"><x-icon name="sparkles" /></span>
                <div class="flex-1">
                    <p class="card-title">Start-Vorlagen übernehmen?</p>
                    <p class="mt-0.5 text-sm text-muted">Basis, Städtetrip, Strand, Wandern und Bikepacking – fertig gefüllt und jederzeit anpassbar.</p>
                </div>
                <div class="flex gap-2">
                    <a href="{{ route('templates.index') }}" class="btn btn-primary">Ansehen</a>
                    <form method="POST" action="{{ route('starter-templates.dismiss') }}">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn btn-ghost">Nein danke</button>
                    </form>
                </div>
            </div>
        @endif

        @if ($upcoming->isEmpty() && $past->isEmpty())
            <div class="card flex flex-col items-center px-6 py-14 text-center">
                <span class="inline-flex size-16 items-center justify-center rounded-full bg-accent-soft text-accent-ink"><x-icon name="luggage" size="30" /></span>
                <h2 class="mt-5 text-xl font-extrabold">Noch keine Reise geplant</h2>
                <p class="mt-2 max-w-sm text-[15px] text-muted">Leg deine erste Packliste an – allein oder gemeinsam mit Freunden.</p>
                <div class="mt-6 flex flex-wrap justify-center gap-2">
                    <a href="{{ route('trips.create', ['mode' => 'solo']) }}" class="btn btn-primary"><x-icon name="user" size="18" /> Für mich</a>
                    <a href="{{ route('trips.create', ['mode' => 'group']) }}" class="btn btn-secondary"><x-icon name="users" size="18" /> Für eine Gruppe</a>
                </div>
            </div>
        @endif

        @foreach (['Demnächst' => $upcoming, 'Vergangen' => $past] as $heading => $trips)
            @continue($trips->isEmpty())
            <section class="mb-10" aria-labelledby="section-{{ $loop->index }}">
                <h2 id="section-{{ $loop->index }}" class="eyebrow mb-3">{{ $heading }} · {{ $trips->count() }}</h2>

                <div x-show="view === 'tiles'" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($trips as $trip)
                        <x-trip-tile :trip="$trip" />
                    @endforeach
                </div>

                <div x-cloak x-show="view === 'list'" class="card divide-y divide-line-soft overflow-hidden">
                    @foreach ($trips as $trip)
                        <x-trip-row :trip="$trip" />
                    @endforeach
                </div>
            </section>
        @endforeach
    </div>
</x-layouts.app>
