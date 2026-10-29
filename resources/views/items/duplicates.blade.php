<x-layouts.app title="Katalog aufräumen">
    <div class="mx-auto max-w-2xl">
        <div class="mb-5 flex items-start gap-2">
            <a href="{{ route('items.index') }}" class="icon-btn -ml-2" aria-label="Zurück zum Katalog"><x-icon name="arrow-left" /></a>
            <div>
                <h1 class="page-title">Katalog aufräumen</h1>
                <p class="mt-2 text-[15px] text-muted">Diese Gegenstände sehen doppelt aus. Beim Zusammenführen ziehen Vorlagen, Packlisten und Outfits mit – bei doppelten Mengen gilt die größere.</p>
            </div>
        </div>

        @if ($pairs->isEmpty())
            <div class="card flex flex-col items-center px-6 py-12 text-center">
                <span class="inline-flex size-14 items-center justify-center rounded-full bg-accent-soft text-accent-ink"><x-icon name="check" size="26" /></span>
                <p class="mt-4 text-lg font-extrabold">Alles aufgeräumt</p>
                <p class="mt-1 text-[15px] text-muted">Keine Doppelungen in Sicht.</p>
                <a href="{{ route('items.index') }}" class="btn btn-secondary mt-5">Zum Katalog</a>
            </div>
        @else
            <div class="space-y-4">
                @foreach ($pairs as ['a' => $a, 'b' => $b, 'reason' => $reason])
                    @php($uid = $a->id.'-'.$b->id)
                    {{-- Vorschlag: was in mehr Vorlagen steckt, sonst das ältere --}}
                    @php($usedA = $usage[$a->id]->templates_count ?? 0)
                    @php($usedB = $usage[$b->id]->templates_count ?? 0)
                    @php($suggested = $usedA === $usedB ? min($a->id, $b->id) : ($usedA > $usedB ? $a->id : $b->id))
                    <section class="card p-5" x-data="{ keep: {{ $suggested }} }">
                        <p class="text-xs font-bold tracking-[0.06em] text-muted uppercase">{{ $reason }}</p>
                        <fieldset class="mt-3 grid gap-2 sm:grid-cols-2">
                            <legend class="sr-only">Welcher Name bleibt?</legend>
                            @foreach ([$a, $b] as $item)
                                @php($templates = $usage[$item->id]->templates_count ?? 0)
                                <label class="flex min-h-[64px] cursor-pointer items-center gap-3 rounded-chip border border-line px-3 py-2 has-checked:border-accent has-checked:bg-accent-soft/50">
                                    <input type="radio" name="keep-{{ $uid }}" value="{{ $item->id }}" x-model.number="keep" class="size-5 accent-[var(--accent)]">
                                    <span class="min-w-0">
                                        <span class="block truncate text-[15px] font-bold">{{ $item->name }}</span>
                                        <span class="flex items-center gap-1 text-xs font-semibold text-muted">
                                            <x-icon :name="$item->categoryGroup()->icon" size="12" /> {{ $item->categoryGroup()->label }}@if ($templates) · in {{ $templates }} {{ $templates === 1 ? 'Vorlage' : 'Vorlagen' }}@endif @if ($item->weightLabel()) · {{ $item->weightLabel() }}@endif
                                        </span>
                                    </span>
                                </label>
                            @endforeach
                        </fieldset>
                        <div class="mt-4 flex flex-wrap items-center gap-2">
                            <form method="POST" action="{{ route('items.duplicates.merge') }}" data-keep-scroll>
                                @csrf
                                <input type="hidden" name="keep_id" :value="keep">
                                <input type="hidden" name="drop_id" :value="keep === {{ $a->id }} ? {{ $b->id }} : {{ $a->id }}">
                                <button type="submit" class="btn btn-primary"><x-icon name="layers" size="18" /> Zusammenführen</button>
                            </form>
                            <form method="POST" action="{{ route('items.duplicates.dismiss') }}" data-keep-scroll>
                                @csrf
                                <input type="hidden" name="a_id" value="{{ $a->id }}">
                                <input type="hidden" name="b_id" value="{{ $b->id }}">
                                <button type="submit" class="btn btn-ghost">Sind verschieden</button>
                            </form>
                        </div>
                    </section>
                @endforeach
            </div>
        @endif
    </div>
</x-layouts.app>
