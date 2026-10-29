@props(['label'])
{{-- Eingabezeile für Alpine „quickAdd“: Enter legt an, eine eingefügte Liste wird zeilenweise angelegt. --}}
<form @submit.prevent="add" class="flex items-center gap-2 py-1.5 pr-2 pl-5">
    <x-icon name="plus" size="16" class="shrink-0 text-muted" />
    <input x-ref="input" x-model="name" @paste="paste" maxlength="120" enterkeyhint="enter" autocomplete="off"
           placeholder="Neuer Eintrag – Enter zum Hinzufügen" aria-label="Neuer Gegenstand in {{ $label }}"
           class="min-h-11 min-w-0 flex-1 bg-transparent text-[15px] font-semibold outline-none placeholder:font-normal placeholder:text-muted">
    <span x-show="busy && queue.length" x-cloak class="text-xs font-bold text-muted" x-text="`noch ${queue.length}`"></span>
    <button type="submit" x-show="name.trim()" x-cloak class="icon-btn text-accent" aria-label="Hinzufügen"><x-icon name="check" size="18" /></button>
</form>
<p x-show="error" x-cloak x-text="error" class="field-error px-5 pb-3" role="alert"></p>
<p class="sr-only" aria-live="polite" x-text="added.length ? `${added[added.length - 1].name} hinzugefügt` : ''"></p>
