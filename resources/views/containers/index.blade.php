@php use App\Enums\ContainerKind; use App\Models\Container; @endphp
<x-layouts.app title="Behältnisse">
    <div class="mx-auto max-w-2xl">
        <div class="mb-5 flex items-start gap-2">
            <a href="{{ route('items.index') }}" class="icon-btn -ml-2" aria-label="Zurück zum Katalog"><x-icon name="arrow-left" /></a>
            <div>
                <h1 class="page-title">Behältnisse</h1>
                <p class="mt-2 text-[15px] text-muted">Koffer, Rucksäcke, Euroboxen – worin du packst. Pro Reise wählst du aus, was mitkommt, und siehst, was wo liegt. Ganz optional.</p>
            </div>
        </div>

        <div class="space-y-3">
            @foreach ($containers as $container)
                @php($bag = 'edit-'.$container->id)
                <div class="card" x-data="{ edit: {{ $errors->getBag($bag)->any() ? 'true' : 'false' }} }">
                    <div class="flex min-h-[64px] items-center gap-3 px-5 py-3">
                        <span class="inline-flex size-10 shrink-0 items-center justify-center rounded-chip bg-accent-soft text-accent-ink"><x-icon :name="$container->kind->icon()" /></span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-[15px] font-bold">{{ $container->name }}</p>
                            <p class="text-xs font-semibold text-muted">
                                {{ $container->kind->label() }}@if ($container->limitsLabel()) · {{ $container->limitsLabel() }}@endif @if ($container->empty_weight_g) · leer {{ Container::kg($container->empty_weight_g) }}@endif
                            </p>
                        </div>
                        <button type="button" class="icon-btn" @click="edit = !edit" :aria-expanded="edit" aria-label="{{ $container->name }} bearbeiten"><x-icon name="pencil" size="18" /></button>
                    </div>
                    <div x-show="edit" x-cloak class="border-t border-line-soft px-5 py-4">
                        @include('containers._form', ['container' => $container, 'bag' => $bag])
                        <form method="POST" action="{{ route('containers.destroy', $container) }}" class="mt-3">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-danger min-h-10 text-sm"><x-icon name="trash" size="16" /> Löschen</button>
                        </form>
                    </div>
                </div>
            @endforeach

            <div class="card border-dashed p-5" x-data="{ open: {{ $errors->getBag('create')->any() || $containers->isEmpty() ? 'true' : 'false' }} }">
                <button type="button" x-show="!open" @click="open = true" class="flex min-h-12 w-full items-center justify-center gap-2 text-[15px] font-bold text-muted hover:text-ink">
                    <x-icon name="plus" size="20" /> Behältnis anlegen
                </button>
                <div x-show="open" x-cloak>
                    <p class="card-title mb-3">Neues Behältnis</p>
                    @include('containers._form', ['container' => new Container(['kind' => ContainerKind::Suitcase]), 'bag' => 'create'])
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>
