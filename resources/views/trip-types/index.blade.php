@php use App\Models\CustomTripType; @endphp
<x-layouts.app title="Reisearten">
    <div class="mx-auto max-w-2xl">
        <div class="mb-5 flex items-start gap-2">
            <a href="{{ route('home') }}" class="icon-btn -ml-2" aria-label="Zurück zur Übersicht"><x-icon name="arrow-left" /></a>
            <div>
                <h1 class="page-title">Reisearten</h1>
                <p class="mt-2 text-[15px] text-muted">Eigene Reisearten zusätzlich zu den festen, z. B. „Geschäftsreise“ oder „Festival“. Heißt eine Vorlage genauso, wird sie für neue Packlisten vorgeschlagen.</p>
            </div>
        </div>

        <div class="space-y-3">
            @foreach ($types as $type)
                @php($bag = 'edit-'.$type->id)
                <div class="card overflow-hidden" x-data="{ edit: {{ $errors->getBag($bag)->any() ? 'true' : 'false' }} }">
                    <div class="flex min-h-[64px] items-center gap-3 p-3">
                        <span class="inline-flex size-12 shrink-0 items-center justify-center rounded-chip text-white" style="background: {{ $type->gradient() }}"><x-icon :name="$type->icon" /></span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-[15px] font-bold">{{ $type->name }}</p>
                            <p class="text-xs font-semibold text-muted">{{ $type->trips_count }} {{ $type->trips_count === 1 ? 'Reise' : 'Reisen' }}</p>
                        </div>
                        <button type="button" class="icon-btn" @click="edit = !edit" :aria-expanded="edit" aria-label="{{ $type->name }} bearbeiten"><x-icon name="pencil" size="18" /></button>
                    </div>
                    <div x-show="edit" x-cloak class="border-t border-line-soft px-5 py-4" x-data="{ confirming: false }">
                        @include('trip-types._form', ['type' => $type, 'bag' => $bag])
                        <div class="mt-4 border-t border-line-soft pt-4">
                            <button type="button" class="btn btn-danger min-h-10 text-sm" x-show="!confirming" @click="confirming = true"><x-icon name="trash" size="16" /> Löschen</button>
                            <form method="POST" action="{{ route('trip-types.destroy', $type) }}" x-show="confirming" x-cloak class="flex flex-wrap items-center gap-2">
                                @csrf @method('DELETE')
                                <p class="w-full text-sm text-muted">Reisen dieser Art bleiben erhalten und stehen danach unter „Sonstiges“.</p>
                                <button type="submit" class="btn bg-warn text-white hover:bg-warn-ink min-h-10 text-sm">Ja, löschen</button>
                                <button type="button" class="btn btn-secondary min-h-10 text-sm" @click="confirming = false">Abbrechen</button>
                            </form>
                        </div>
                    </div>
                </div>
            @endforeach

            <div id="neu" class="card border-dashed scroll-mt-24 p-5" x-data="{ open: {{ $errors->getBag('create')->any() || $types->isEmpty() ? 'true' : 'false' }} }">
                <button type="button" x-show="!open" @click="open = true" class="flex min-h-12 w-full items-center justify-center gap-2 text-[15px] font-bold text-muted hover:text-ink">
                    <x-icon name="plus" size="20" /> Reiseart anlegen
                </button>
                <div x-show="open" x-cloak>
                    <p class="card-title mb-3">Neue Reiseart</p>
                    @include('trip-types._form', ['type' => new CustomTripType(['icon' => 'briefcase', 'color' => 'pine']), 'bag' => 'create'])
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>
