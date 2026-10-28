<?php

namespace App\Services;

use App\Enums\EntrySource;
use App\Enums\ItemCategory;
use App\Models\Item;
use App\Models\OutfitDay;
use App\Models\OutfitSlot;
use App\Models\PacklistEntry;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Outfit-Planer: pro Tag Slots (Tag, Abend, Sport …) mit Kleidungsteilen.
 *
 * Mengen für die Packliste:
 * - Teile ohne „pro Tag“: 1, sobald sie in mindestens einem Slot vorkommen.
 * - Teile mit „pro Tag“ (Socken, Unterwäsche): Die Reise wird an Waschtagen geteilt –
 *   ein Waschtag beendet einen Abschnitt, gewaschen wird abends. Gezählt wird, wie oft
 *   das Teil in einem Abschnitt vorkommt (Tag + Sport am selben Tag = 2); die Menge ist
 *   der größte Abschnitt.
 */
class OutfitService
{
    public function __construct(private readonly PacklistService $packlists) {}

    /**
     * Legt fehlende Tage für den Reisezeitraum an (mit Slot „Tag“) und entfernt Tage,
     * die nach einer Änderung des Zeitraums nicht mehr dazugehören.
     *
     * @return Collection<int, OutfitDay>
     */
    public function days(Trip $trip, User $user): Collection
    {
        $existing = OutfitDay::where(['trip_id' => $trip->id, 'user_id' => $user->id])->get()->keyBy(fn (OutfitDay $d) => $d->date->toDateString());

        $wanted = collect();
        for ($date = $trip->start_date->copy(); $date->lte($trip->end_date); $date->addDay()) {
            $wanted->push($date->toDateString());
        }

        foreach ($wanted->diff($existing->keys()) as $date) {
            $day = OutfitDay::create(['trip_id' => $trip->id, 'user_id' => $user->id, 'date' => $date]);
            $day->slots()->create(['name' => OutfitSlot::DEFAULT_NAME, 'position' => 0]);
        }

        OutfitDay::where(['trip_id' => $trip->id, 'user_id' => $user->id])
            ->where(fn ($query) => $query
                ->whereDate('date', '<', $trip->start_date->toDateString())
                ->orWhereDate('date', '>', $trip->end_date->toDateString()))
            ->delete();

        return OutfitDay::with('slots.items')
            ->where(['trip_id' => $trip->id, 'user_id' => $user->id])
            ->orderBy('date')
            ->get();
    }

    /**
     * Nur lesen, ohne Tage anzulegen – für freigegebene Outfits anderer Personen.
     *
     * @return Collection<int, OutfitDay>
     */
    public function existingDays(Trip $trip, User $user): Collection
    {
        return OutfitDay::with('slots.items')
            ->where(['trip_id' => $trip->id, 'user_id' => $user->id])
            ->whereDate('date', '>=', $trip->start_date->toDateString())
            ->whereDate('date', '<=', $trip->end_date->toDateString())
            ->orderBy('date')
            ->get();
    }

    /**
     * Was man bei der Anreise am Körper trägt und deshalb nicht ins Gepäckgewicht zählt:
     * der Slot „Anreise“ am ersten Tag, sonst dessen Slot „Tag“.
     *
     * @return array<int, int> item_id => getragene Stückzahl
     */
    public function wornOnArrival(Trip $trip, User $user): array
    {
        $first = OutfitDay::with('slots.items')
            ->where(['trip_id' => $trip->id, 'user_id' => $user->id])
            ->whereDate('date', $trip->start_date->toDateString())
            ->first();

        $slot = $first?->slots->firstWhere('name', OutfitSlot::ARRIVAL_NAME)
            ?? $first?->slots->firstWhere('name', OutfitSlot::DEFAULT_NAME);

        return $slot ? $slot->items->mapWithKeys(fn (Item $item) => [$item->id => 1])->all() : [];
    }

    public function addSlot(OutfitDay $day, string $name): OutfitSlot
    {
        return $day->slots()->create([
            'name' => trim($name),
            'position' => (int) $day->slots()->max('position') + 1,
        ]);
    }

    /** Übernimmt die Slots samt Teilen vom Vortag („wie gestern“). */
    public function copyPreviousDay(OutfitDay $day): bool
    {
        $previous = OutfitDay::with('slots.items')
            ->where(['trip_id' => $day->trip_id, 'user_id' => $day->user_id])
            ->where('date', '<', $day->date->toDateString())
            ->orderByDesc('date')
            ->first();

        if ($previous === null) {
            return false;
        }

        DB::transaction(function () use ($day, $previous) {
            $day->slots()->delete();
            foreach ($previous->slots as $slot) {
                $copy = $day->slots()->create(['name' => $slot->name, 'position' => $slot->position]);
                $copy->items()->attach($slot->items->modelKeys());
            }
        });

        return true;
    }

    /**
     * Mengen pro Kleidungsstück für die Packliste.
     *
     * @param  Collection<int, OutfitDay>  $days  mit geladenen slots.items
     * @return array<int, int> item_id => Menge
     */
    public function quantities(Collection $days): array
    {
        $segments = [[]];
        foreach ($days as $day) {
            $segments[array_key_last($segments)][] = $day;
            if ($day->laundry_day) {
                $segments[] = [];
            }
        }

        $items = [];
        $max = [];
        foreach ($segments as $segment) {
            $counts = [];
            foreach ($segment as $day) {
                foreach ($day->slots as $slot) {
                    foreach ($slot->items as $item) {
                        $items[$item->id] = $item;
                        $counts[$item->id] = ($counts[$item->id] ?? 0) + 1;
                    }
                }
            }
            foreach ($counts as $id => $count) {
                $max[$id] = max($max[$id] ?? 0, $count);
            }
        }

        return collect($items)->mapWithKeys(fn (Item $item, int $id) => [$id => $item->per_day ? $max[$id] : 1])->all();
    }

    /**
     * Wochenübersicht: Zeilen = Kleidungsteile (nach Kategorie), Spalten = Tage.
     *
     * @param  Collection<int, OutfitDay>  $days
     * @return array<string, list<array{item: Item, worn: array<int, int>, total: int}>> Kategorie => Zeilen
     */
    public function overview(Collection $days): array
    {
        $rows = [];
        foreach ($days as $index => $day) {
            foreach ($day->slots as $slot) {
                foreach ($slot->items as $item) {
                    $rows[$item->id] ??= ['item' => $item, 'worn' => [], 'total' => 0];
                    $rows[$item->id]['worn'][$index] = ($rows[$item->id]['worn'][$index] ?? 0) + 1;
                }
            }
        }

        foreach ($rows as &$row) {
            $row['total'] = count($row['worn']); // an wie vielen Tagen getragen
        }
        unset($row);

        return collect($rows)
            ->sortBy(fn ($row) => [$row['item']->category->position(), mb_strtolower($row['item']->name)])
            ->groupBy(fn ($row) => $row['item']->category->value)
            ->map(fn ($group) => $group->values()->all())
            ->all();
    }

    /**
     * Schreibt die berechneten Kleidungsmengen in die Packliste.
     * Von Hand geänderte Mengen bleiben stehen; Teile, die in keinem Outfit mehr
     * vorkommen, fallen an die Vorlagen zurück oder weg.
     *
     * @return int Anzahl übernommener Teile
     */
    public function applyToPacklist(Trip $trip, User $user): int
    {
        return DB::transaction(function () use ($trip, $user) {
            $quantities = $this->quantities($this->days($trip, $user));

            foreach ($quantities as $itemId => $quantity) {
                $entry = PacklistEntry::firstOrNew(['trip_id' => $trip->id, 'user_id' => $user->id, 'item_id' => $itemId]);
                $entry->fill([
                    'source' => EntrySource::Outfit,
                    'excluded' => false,
                    'quantity' => $entry->quantity_locked ? $entry->quantity : $quantity,
                ])->save();
            }

            $orphans = PacklistEntry::where(['trip_id' => $trip->id, 'user_id' => $user->id, 'source' => EntrySource::Outfit])
                ->whereNotIn('item_id', array_keys($quantities));
            // Zurück an die Vorlagen; was dort nicht vorkommt, entfernt sync() anschließend.
            $orphans->update(['source' => EntrySource::Template, 'quantity_locked' => false]);

            $this->packlists->sync($trip, $user);

            return count($quantities);
        });
    }

    /** @return Collection<int, Item> Kleidungsteile aus dem Katalog für die Auswahl */
    public function clothingItems(User $user): Collection
    {
        return Item::accessibleBy($user)
            ->whereIn('category', collect(ItemCategory::clothing())->map->value)
            ->orderBy('name')
            ->get();
    }
}
