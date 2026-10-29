@props(['trip', 'group'])
{{-- Schnelleingabe unten in einer Karte der Packliste, mit Vorschlägen aus dem Katalog (Alpine „packlistQuickAdd“). --}}
<div x-data="packlistQuickAdd(@js(route('packlist.store', $trip)), @js($group->key))" x-show="!editing && direction === 'there'" @click.outside="open = false">
    <ul x-show="added.length" x-cloak class="divide-y divide-line-soft border-t border-line-soft">
        <template x-for="entry in added" :key="entry.id">
            <li x-show="entry.id in current" class="flex min-h-[52px] items-center">
                <button type="button" role="checkbox" :aria-checked="!!current[entry.id]" @click="toggle(entry.id, entry.toggle_url, entry.return_url)" class="flex min-w-0 flex-1 items-center gap-3 py-2.5 pl-5 text-left">
                    <span class="inline-flex size-6 shrink-0 items-center justify-center rounded-[7px] border-2 transition" :class="current[entry.id] ? 'border-accent bg-accent text-white' : 'border-line bg-surface text-transparent'">
                        <x-icon name="check" size="16" :stroke-width="3" />
                    </span>
                    <span class="min-w-0">
                        <span class="block truncate text-[15px] font-semibold transition" :class="current[entry.id] && 'text-muted line-through'" x-text="entry.name"></span>
                        <span class="block truncate text-xs font-bold text-accent-ink" x-text="entry.group === @js($group->key) ? 'neu' : `neu · steht unter „${entry.group_label}“`"></span>
                    </span>
                </button>
                <span class="ml-2 rounded-full bg-line-soft px-2.5 py-1 text-[13px] font-extrabold" x-text="`${entry.quantity}×`"></span>
                <button type="button" @click="quickRemove(entry.id, entry.destroy_url, entry.name)" class="mr-1 ml-1 inline-flex min-h-11 min-w-10 items-center justify-center rounded-chip text-muted/70 hover:bg-warn/10 hover:text-warn-ink" :aria-label="`${entry.name} entfernen`">
                    <x-icon name="x" size="16" />
                </button>
            </li>
        </template>
    </ul>
    <form @submit.prevent="enter()" class="flex items-center gap-2 border-t border-line-soft py-1.5 pr-2 pl-5">
        <x-icon name="plus" size="16" class="shrink-0 text-muted" />
        <input x-ref="input" x-model="name" @input="typed()" @focus="open = true" @keydown.arrow-down.prevent="move(1)" @keydown.arrow-up.prevent="move(-1)" @keydown.escape="open = false"
               maxlength="120" enterkeyhint="enter" autocomplete="off" role="combobox" :aria-expanded="open && suggestions.length > 0" aria-autocomplete="list" aria-controls="suggest-{{ $group->key }}"
               :aria-activedescendant="active >= 0 ? `suggest-{{ $group->key }}-${active}` : null"
               placeholder="Hinzufügen – tippen für Vorschläge" aria-label="Zu {{ $group->label }} hinzufügen"
               class="min-h-11 min-w-0 flex-1 bg-transparent text-[15px] font-semibold outline-none placeholder:font-normal placeholder:text-muted">
        <button type="submit" x-show="name.trim()" x-cloak class="icon-btn text-accent" :disabled="busy" aria-label="Hinzufügen"><x-icon name="check" size="18" /></button>
    </form>
    <ul id="suggest-{{ $group->key }}" role="listbox" x-show="open && suggestions.length" x-cloak class="border-t border-line-soft bg-line-soft/40 py-1">
        <template x-for="(s, i) in suggestions" :key="s.id">
            <li :id="`suggest-{{ $group->key }}-${i}`" role="option" :aria-selected="i === active">
                <button type="button" tabindex="-1" @mousedown.prevent @click="add({ item_id: s.id })" @mouseenter="active = i"
                        class="flex min-h-11 w-full items-center gap-3 px-5 text-left" :class="i === active && 'bg-line-soft'">
                    <x-icon name="plus" size="14" class="text-accent" />
                    <span class="min-w-0 flex-1 truncate text-[15px] font-semibold" x-text="s.name"></span>
                    <span x-show="s.group !== @js($group->key)" class="shrink-0 text-xs font-semibold text-muted" x-text="s.label"></span>
                </button>
            </li>
        </template>
        <li x-show="name.trim() && !suggestions.some(s => s.key === normalizeName(name))" class="px-5 py-1.5 text-xs font-semibold text-muted">
            Enter: „<span x-text="name.trim()"></span>“ nur für diese Reise
        </li>
    </ul>
    <p x-show="error" x-cloak x-text="error" class="field-error px-5 pb-3" role="alert"></p>
</div>
