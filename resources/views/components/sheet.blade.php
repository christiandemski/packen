@props(['name', 'title'])
{{-- Unten aufgehendes Blatt (Handy) bzw. zentrierter Dialog (Desktop). Öffnen mit $dispatch('open-sheet', '<name>'). --}}
<div x-data="{ open: false }" x-cloak
     @open-sheet.window="if ($event.detail === @js($name)) { open = true; $nextTick(() => $refs.panel.querySelector('[autofocus]')?.focus()) }"
     @keydown.escape.window="open = false">
    <div x-show="open" x-transition.opacity class="fixed inset-0 z-40 bg-ink/40" @click="open = false"></div>
    <div x-show="open" x-ref="panel" role="dialog" aria-modal="true" aria-label="{{ $title }}"
         x-transition:enter="transition duration-200 ease-out" x-transition:enter-start="translate-y-8 opacity-0" x-transition:enter-end="translate-y-0 opacity-100"
         class="fixed inset-x-0 bottom-0 z-50 max-h-[88dvh] overflow-y-auto rounded-t-card bg-surface p-5 pb-[calc(1.25rem+env(safe-area-inset-bottom))] sm:inset-x-auto sm:top-1/2 sm:bottom-auto sm:left-1/2 sm:w-[520px] sm:-translate-x-1/2 sm:-translate-y-1/2 sm:rounded-card">
        <div class="mb-4 flex items-center justify-between gap-3">
            <h2 class="text-xl font-extrabold">{{ $title }}</h2>
            <button type="button" class="icon-btn -mr-2" @click="open = false" aria-label="Schließen"><x-icon name="x" /></button>
        </div>
        {{ $slot }}
    </div>
</div>
