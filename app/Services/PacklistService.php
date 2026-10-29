<?php

namespace App\Services;

use App\Enums\EntrySource;
use App\Enums\PacklistGroup;
use App\Models\Category;
use App\Models\Item;
use App\Models\PacklistEntry;
use App\Models\Template;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Setzt die persönliche Packliste einer Person aus Vorlagen zusammen.
 *
 * Regeln:
 * - Kommt ein Gegenstand in mehreren gewählten Vorlagen vor, zählt die größte Menge
 *   (Regenjacke in „Städtetrip“ und „Wandern“ → 1, nicht 2).
 * - „Pro Tag“-Gegenstände: Menge × Reisetage.
 * - Von Hand geänderte Mengen (quantity_locked) und bewusst entfernte Einträge (excluded)
 *   überstehen jedes Neuberechnen.
 * - Händisch hinzugefügte Einträge fasst die Berechnung nie an.
 */
class PacklistService
{
    public function __construct(private readonly ContainerService $containers) {}

    /** @param list<int> $templateIds */
    public function setTemplates(Trip $trip, User $user, array $templateIds): void
    {
        DB::transaction(function () use ($trip, $user, $templateIds) {
            $ids = Template::accessibleBy($user)->whereIn('id', $templateIds)->pluck('id');

            DB::table('trip_templates')->where(['trip_id' => $trip->id, 'user_id' => $user->id])
                ->whereNotIn('template_id', $ids)->delete();

            foreach ($ids as $id) {
                DB::table('trip_templates')->insertOrIgnore([
                    'trip_id' => $trip->id, 'user_id' => $user->id, 'template_id' => $id,
                ]);
            }

            $this->sync($trip, $user);
        });
    }

    /** Gleicht die Vorlagen-Einträge einer Person mit den gewählten Vorlagen ab. */
    public function sync(Trip $trip, User $user): void
    {
        $wanted = $this->wantedQuantities($trip, $user);

        /** @var Collection<int, PacklistEntry> $existing */
        $existing = PacklistEntry::where(['trip_id' => $trip->id, 'user_id' => $user->id])
            ->whereNotNull('item_id')
            ->get()
            ->keyBy('item_id');

        foreach ($wanted as $itemId => $quantity) {
            $entry = $existing->get($itemId);

            if ($entry === null) {
                PacklistEntry::create([
                    'trip_id' => $trip->id,
                    'user_id' => $user->id,
                    'item_id' => $itemId,
                    'quantity' => $quantity,
                    'source' => EntrySource::Template,
                ]);
            } elseif ($entry->source === EntrySource::Template && ! $entry->quantity_locked && $entry->quantity !== $quantity) {
                $entry->update(['quantity' => $quantity]);
            }
        }

        // Vorlagen-Einträge, die keine gewählte Vorlage mehr enthält, fallen weg.
        PacklistEntry::where(['trip_id' => $trip->id, 'user_id' => $user->id, 'source' => EntrySource::Template])
            ->whereNotIn('item_id', array_keys($wanted))
            ->delete();

        $this->containers->applyDefaults($trip, $user);
    }

    /** Für alle Mitglieder neu berechnen, z. B. nach Änderung des Zeitraums. */
    public function syncTrip(Trip $trip): void
    {
        foreach ($trip->members()->get() as $member) {
            $this->sync($trip, $member);
        }
    }

    /** Nach Änderungen an einer Vorlage alle noch nicht vergangenen Reisen nachziehen. */
    public function syncTemplate(Template $template): void
    {
        DB::table('trip_templates')
            ->join('trips', 'trips.id', '=', 'trip_templates.trip_id')
            ->where('trip_templates.template_id', $template->id)
            ->where('trips.end_date', '>=', today()->toDateString())
            ->select('trip_templates.trip_id', 'trip_templates.user_id')
            ->get()
            ->each(fn ($row) => $this->sync(Trip::find($row->trip_id), User::find($row->user_id)));
    }

    /**
     * Beim Löschen einer Vorlage bleiben bestehende Listen erhalten: Ihre Einträge
     * gelten danach als von Hand hinzugefügt.
     */
    public function detachTemplate(Template $template): void
    {
        DB::transaction(function () use ($template) {
            $links = DB::table('trip_templates')->where('template_id', $template->id)->get();

            foreach ($links as $link) {
                $otherTemplates = DB::table('trip_templates')
                    ->where(['trip_id' => $link->trip_id, 'user_id' => $link->user_id])
                    ->where('template_id', '!=', $template->id)
                    ->pluck('template_id');
                $stillCovered = DB::table('template_items')->whereIn('template_id', $otherTemplates)->pluck('item_id');

                PacklistEntry::where(['trip_id' => $link->trip_id, 'user_id' => $link->user_id, 'source' => EntrySource::Template])
                    ->whereIn('item_id', $template->items()->pluck('items.id'))
                    ->whereNotIn('item_id', $stillCovered)
                    ->update(['source' => EntrySource::Manual]);
            }

            DB::table('trip_templates')->where('template_id', $template->id)->delete();
        });
    }

    /** @param array{item_id?: ?int, custom_name?: ?string, group?: ?string, quantity?: int, note?: ?string} $data */
    public function addEntry(Trip $trip, User $user, array $data): PacklistEntry
    {
        $quantity = max(1, (int) ($data['quantity'] ?? 1));

        if (! empty($data['item_id'])) {
            $entry = PacklistEntry::firstOrNew(['trip_id' => $trip->id, 'user_id' => $user->id, 'item_id' => $data['item_id']]);

            if ($entry->exists && ! $entry->excluded) {
                return $entry;
            }

            $entry->fill([
                'quantity' => $quantity,
                'quantity_locked' => $entry->exists,
                'excluded' => false,
                'source' => $entry->source ?? EntrySource::Manual,
                'note' => $data['note'] ?? null,
            ])->save();

            $this->containers->applyDefaults($trip, $user);

            return $entry->refresh();
        }

        return PacklistEntry::create([
            'trip_id' => $trip->id,
            'user_id' => $user->id,
            'custom_name' => trim((string) $data['custom_name']),
            'group' => Category::packlistChoices($user, withHidden: true)->firstWhere('key', $data['group'] ?? null)?->key ?? PacklistGroup::Misc->value,
            'quantity' => $quantity,
            'source' => EntrySource::Manual,
            'note' => $data['note'] ?? null,
        ]);
    }

    public function updateQuantity(PacklistEntry $entry, int $quantity): void
    {
        $entry->update(['quantity' => $quantity, 'quantity_locked' => true]);
    }

    /** Vorlagen-Einträge werden nur ausgeblendet, damit sie beim Neuberechnen nicht zurückkommen. */
    public function removeEntry(PacklistEntry $entry): void
    {
        if ($entry->source === EntrySource::Template) {
            $entry->update(['excluded' => true, 'packed' => false, 'packed_at' => null]);
        } else {
            $entry->delete();
        }
    }

    /** Bewusst entfernten Vorlagen-Eintrag wieder auf die Liste holen (Menge wieder aus der Vorlage). */
    public function restoreEntry(PacklistEntry $entry): void
    {
        $entry->update(['excluded' => false, 'quantity_locked' => false]);
        $this->sync($entry->trip, $entry->user);
    }

    /**
     * Was jemand für diese Reise bewusst weggelassen hat – ohne die Einträge,
     * die gerade als Anfrage bei der Gruppe liegen.
     *
     * @return Collection<int, PacklistEntry>
     */
    public function excludedEntries(Trip $trip, User $user): Collection
    {
        return PacklistEntry::with('item')
            ->where(['trip_id' => $trip->id, 'user_id' => $user->id, 'excluded' => true, 'source' => EntrySource::Template])
            ->whereNotIn('id', DB::table('group_items')->where('trip_id', $trip->id)->whereNotNull('source_entry_id')->select('source_entry_id'))
            ->get()
            ->sortBy(fn (PacklistEntry $e) => mb_strtolower($e->name()))
            ->values();
    }

    /**
     * Was schon auf früheren Reisen weggelassen wurde: Vorschlag, es aus der Vorlage zu streichen.
     *
     * @param  Collection<int, PacklistEntry>  $excluded  siehe excludedEntries()
     * @return array<int, array{times: int, templates: Collection<int, Template>}> entry_id => Hinweis
     */
    public function exclusionHints(Trip $trip, User $user, Collection $excluded): array
    {
        $itemIds = $excluded->pluck('item_id')->filter()->values();
        if ($itemIds->isEmpty()) {
            return [];
        }

        $earlier = PacklistEntry::where(['user_id' => $user->id, 'excluded' => true, 'source' => EntrySource::Template])
            ->where('trip_id', '!=', $trip->id)
            ->whereIn('item_id', $itemIds)
            ->whereNotIn('id', DB::table('group_items')->whereNotNull('source_entry_id')->select('source_entry_id'))
            ->groupBy('item_id')
            ->selectRaw('item_id, count(distinct trip_id) as trips')
            ->pluck('trips', 'item_id');

        // Aus welchen der gewählten Vorlagen kommt der Gegenstand?
        $links = DB::table('trip_templates')
            ->join('template_items', 'template_items.template_id', '=', 'trip_templates.template_id')
            ->where(['trip_templates.trip_id' => $trip->id, 'trip_templates.user_id' => $user->id])
            ->whereIn('template_items.item_id', $earlier->keys())
            ->get(['template_items.item_id', 'trip_templates.template_id']);
        $templates = Template::whereIn('id', $links->pluck('template_id')->unique())->orderBy('name')->get()->keyBy('id');

        $hints = [];
        foreach ($excluded as $entry) {
            $ids = $links->where('item_id', $entry->item_id)->pluck('template_id');
            if ($earlier->has($entry->item_id) && $ids->isNotEmpty()) {
                $hints[$entry->id] = ['times' => (int) $earlier[$entry->item_id] + 1, 'templates' => $templates->only($ids->all())->values()];
            }
        }

        return $hints;
    }

    /**
     * Übernimmt Vorlagen-Auswahl und eigene Einträge einer früheren Reise.
     * Bereits vorhandene Gegenstände bleiben unverändert; nichts ist abgehakt.
     */
    public function copyFrom(Trip $source, Trip $target, User $user): int
    {
        return DB::transaction(function () use ($source, $target, $user) {
            $templateIds = DB::table('trip_templates')
                ->where(['trip_id' => $source->id, 'user_id' => $user->id])
                ->pluck('template_id')
                ->merge(DB::table('trip_templates')->where(['trip_id' => $target->id, 'user_id' => $user->id])->pluck('template_id'))
                ->unique()->values()->all();

            // Behältnisse zuerst, damit Standardplätze beim Zusammensetzen greifen.
            $this->containers->setForTrip($target, $user, $this->containers->selectedIds($source, $user)
                ->merge($this->containers->selectedIds($target, $user))->unique()->values()->all());

            $this->setTemplates($target, $user, $templateIds);

            $copied = 0;
            $manual = PacklistEntry::with('item')
                ->where(['trip_id' => $source->id, 'user_id' => $user->id])
                ->where('source', '!=', EntrySource::Template)
                ->visible()
                ->get();

            foreach ($manual as $entry) {
                if ($entry->item_id !== null && PacklistEntry::where(['trip_id' => $target->id, 'user_id' => $user->id, 'item_id' => $entry->item_id])->exists()) {
                    continue;
                }

                PacklistEntry::create([
                    'trip_id' => $target->id,
                    'user_id' => $user->id,
                    'item_id' => $entry->item_id,
                    'custom_name' => $entry->custom_name,
                    'group' => $entry->group,
                    'quantity' => $entry->quantity,
                    'quantity_locked' => $entry->quantity_locked,
                    'source' => EntrySource::Manual,
                    'note' => $entry->note,
                    'container_id' => $entry->container_id,
                ]);
                $copied++;
            }

            // Wo etwas auf der früheren Reise lag, kommt es diesmal wieder hin (sofern noch nicht zugeordnet).
            $places = PacklistEntry::where(['trip_id' => $source->id, 'user_id' => $user->id])
                ->whereNotNull('item_id')->whereNotNull('container_id')->pluck('container_id', 'item_id');
            PacklistEntry::where(['trip_id' => $target->id, 'user_id' => $user->id])->whereNull('container_id')
                ->whereIn('item_id', $places->keys())->get()
                ->each(fn (PacklistEntry $e) => $e->update(['container_id' => $places[$e->item_id]]));

            return $copied;
        });
    }

    /** @return array{packed: int, total: int} */
    public function progress(Trip $trip, User $user): array
    {
        $counts = PacklistEntry::where(['trip_id' => $trip->id, 'user_id' => $user->id])->visible()
            ->selectRaw('count(*) as total_count, sum(case when packed then 1 else 0 end) as packed_count')
            ->first();

        return ['packed' => (int) $counts->packed_count, 'total' => (int) $counts->total_count];
    }

    /**
     * Für die Kacheln: Fortschritt aller Reisen einer Person in einer Abfrage.
     *
     * @param  list<int>  $tripIds
     * @return array<int, array{packed: int, total: int}>
     */
    public function progressForTrips(array $tripIds, User $user): array
    {
        return PacklistEntry::whereIn('trip_id', $tripIds)->where('user_id', $user->id)->visible()
            ->groupBy('trip_id')
            ->selectRaw('trip_id, count(*) as total_count, sum(case when packed then 1 else 0 end) as packed_count')
            ->get()
            ->mapWithKeys(fn ($row) => [$row->trip_id => ['packed' => (int) $row->packed_count, 'total' => (int) $row->total_count]])
            ->all();
    }

    /** @return Collection<int, Item> Gegenstände aus dem Katalog, die noch nicht auf der Liste stehen */
    public function addableItems(Trip $trip, User $user): Collection
    {
        $taken = PacklistEntry::where(['trip_id' => $trip->id, 'user_id' => $user->id])->visible()->whereNotNull('item_id')->pluck('item_id');

        return Item::accessibleBy($user)->with('customCategory')->whereNotIn('id', $taken)->orderBy('name')->get();
    }

    /** @return array<int, int> item_id => Menge */
    private function wantedQuantities(Trip $trip, User $user): array
    {
        $rows = DB::table('trip_templates')
            ->join('template_items', 'template_items.template_id', '=', 'trip_templates.template_id')
            ->where(['trip_templates.trip_id' => $trip->id, 'trip_templates.user_id' => $user->id])
            ->groupBy('template_items.item_id')
            ->selectRaw('template_items.item_id, max(template_items.quantity) as quantity')
            ->pluck('quantity', 'item_id');

        $perDay = Item::whereIn('id', $rows->keys())->where('per_day', true)->pluck('id')->flip();
        $days = $trip->days();

        return $rows->map(fn ($quantity, $itemId) => (int) $quantity * ($perDay->has($itemId) ? $days : 1))->all();
    }
}
