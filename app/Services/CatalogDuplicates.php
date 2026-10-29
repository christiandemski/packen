<?php

namespace App\Services;

use App\Models\Item;
use App\Models\PacklistEntry;
use App\Models\Template;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Findet vermutlich doppelte Gegenstände im Katalog und führt sie zusammen.
 *
 * Als Doppelung gilt:
 * - gleicher Name bis auf Schreibweise („Zahnbürste“ / „zahnbuerste“ / „Zahn-Bürste“),
 * - ein kleiner Tippfehler bei längeren Namen („Sonnencreme“ / „Sonencreme“),
 * - in derselben Kategorie ein Name als eigenes Wort im anderen („Ladekabel“ / „Handy-Ladekabel“).
 * Zusammengesetzte Wörter („Jacke“ / „Regenjacke“) zählen bewusst nicht – das sind meist verschiedene Dinge.
 */
class CatalogDuplicates
{
    public function __construct(private readonly PacklistService $packlists) {}

    /** @return Collection<int, array{a: Item, b: Item, reason: string}> */
    public function pairs(User $user): Collection
    {
        $items = Item::accessibleBy($user)->with('customCategory')->orderBy('name')->get()->values();
        $dismissed = DB::table('item_duplicate_dismissals')->where('owner_id', $user->id)->get()
            ->map(fn ($row) => $row->item_a_id.'-'.$row->item_b_id)->flip();

        $prepared = $items->map(fn (Item $item) => [
            'item' => $item,
            'compact' => self::compact($item->name),
            'words' => self::words($item->name),
        ]);

        $pairs = collect();
        for ($i = 0; $i < $prepared->count(); $i++) {
            for ($j = $i + 1; $j < $prepared->count(); $j++) {
                [$a, $b] = [$prepared[$i], $prepared[$j]];
                $key = min($a['item']->id, $b['item']->id).'-'.max($a['item']->id, $b['item']->id);
                if ($dismissed->has($key) || ! ($reason = $this->reason($a, $b))) {
                    continue;
                }
                $pairs->push(['a' => $a['item'], 'b' => $b['item'], 'reason' => $reason]);
            }
        }

        return $pairs;
    }

    /** „Sind verschieden“ – das Paar wird nicht mehr vorgeschlagen. */
    public function dismiss(User $user, Item $a, Item $b): void
    {
        DB::table('item_duplicate_dismissals')->insertOrIgnore([
            'owner_id' => $user->id,
            'item_a_id' => min($a->id, $b->id),
            'item_b_id' => max($a->id, $b->id),
        ]);
    }

    /**
     * Führt $drop in $keep zusammen: Vorlagen, Packlisten und Outfits zeigen danach auf $keep,
     * bei doppelten Einträgen gilt die größere Menge. Leere Angaben an $keep füllt $drop auf.
     */
    public function merge(Item $keep, Item $drop): void
    {
        DB::transaction(function () use ($keep, $drop) {
            // Vorlagen
            $templateIds = DB::table('template_items')->where('item_id', $drop->id)->pluck('template_id');
            foreach (DB::table('template_items')->where('item_id', $drop->id)->get() as $row) {
                $existing = DB::table('template_items')->where(['template_id' => $row->template_id, 'item_id' => $keep->id])->first();
                if ($existing) {
                    DB::table('template_items')->where('id', $existing->id)->update(['quantity' => max($existing->quantity, $row->quantity)]);
                    DB::table('template_items')->where('id', $row->id)->delete();
                } else {
                    DB::table('template_items')->where('id', $row->id)->update(['item_id' => $keep->id]);
                }
            }

            // Packlisten
            foreach (PacklistEntry::where('item_id', $drop->id)->get() as $entry) {
                $existing = PacklistEntry::where(['trip_id' => $entry->trip_id, 'user_id' => $entry->user_id, 'item_id' => $keep->id])->first();
                if ($existing) {
                    $existing->update([
                        'quantity' => max($existing->quantity, $entry->quantity),
                        'quantity_locked' => $existing->quantity_locked || $entry->quantity_locked,
                        'excluded' => $existing->excluded && $entry->excluded,
                        'packed' => $existing->packed || $entry->packed,
                        'note' => $existing->note ?? $entry->note,
                        'container_id' => $existing->container_id ?? $entry->container_id,
                    ]);
                    $entry->delete();
                } else {
                    $entry->update(['item_id' => $keep->id]);
                }
            }

            // Outfits
            foreach (DB::table('outfit_slot_items')->where('item_id', $drop->id)->get() as $row) {
                DB::table('outfit_slot_items')->where(['outfit_slot_id' => $row->outfit_slot_id, 'item_id' => $keep->id])->exists()
                    ? DB::table('outfit_slot_items')->where('id', $row->id)->delete()
                    : DB::table('outfit_slot_items')->where('id', $row->id)->update(['item_id' => $keep->id]);
            }

            // Fehlende Angaben übernehmen
            $keep->fill(array_filter([
                'weight_g' => $keep->weight_g ?? $drop->weight_g,
                'notes' => $keep->notes ?? $drop->notes,
                'default_container_id' => $keep->default_container_id ?? $drop->default_container_id,
            ], fn ($value) => $value !== null))->save();

            $drop->delete();

            // Mengen in kommenden Reisen neu berechnen (größte Menge je Vorlage gilt)
            Template::whereIn('id', $templateIds)->get()->each(fn (Template $t) => $this->packlists->syncTemplate($t));
        });
    }

    /** @param  array{item: Item, compact: string, words: list<string>}  $a */
    private function reason(array $a, array $b): ?string
    {
        if ($a['compact'] === $b['compact']) {
            return 'gleicher Name';
        }

        $shorter = min(strlen($a['compact']), strlen($b['compact']));
        if ($shorter >= 6 && levenshtein($a['compact'], $b['compact']) <= ($shorter >= 10 ? 2 : 1)) {
            return 'ähnlich geschrieben';
        }

        $sameCategory = $a['item']->categoryGroup()->key === $b['item']->categoryGroup()->key;
        $contains = fn (array $x, array $y) => strlen($x['compact']) >= 5 && count($y['words']) > 1 && in_array($x['compact'], $y['words'], true);
        if ($sameCategory && ($contains($a, $b) || $contains($b, $a))) {
            return 'ein Name steckt im anderen';
        }

        return null;
    }

    /** „Handy-Ladekabel (USB‑C)“ → „handyladekabelusbc“; Umlaute ausgeschrieben */
    private static function compact(string $name): string
    {
        return implode('', self::words($name));
    }

    /** @return list<string> */
    private static function words(string $name): array
    {
        $name = strtr(Str::lower($name), ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss']);

        return array_values(array_filter(preg_split('/[^a-z0-9]+/', Str::ascii($name)) ?: []));
    }
}
