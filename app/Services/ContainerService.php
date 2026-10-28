<?php

namespace App\Services;

use App\Enums\ContainerKind;
use App\Models\Container;
use App\Models\PacklistEntry;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Behältnisse (Koffer, Rucksack, Eurobox …) einer Person auf einer Reise.
 * Rein optional: Wer keine auswählt, bemerkt davon nichts.
 */
class ContainerService
{
    /** @param list<int> $containerIds */
    public function setForTrip(Trip $trip, User $user, array $containerIds): void
    {
        DB::transaction(function () use ($trip, $user, $containerIds) {
            $ids = Container::accessibleBy($user)->whereIn('id', $containerIds)->pluck('id');

            DB::table('trip_containers')->where(['trip_id' => $trip->id, 'user_id' => $user->id])
                ->whereNotIn('container_id', $ids)->delete();
            foreach ($ids as $id) {
                DB::table('trip_containers')->insertOrIgnore(['trip_id' => $trip->id, 'user_id' => $user->id, 'container_id' => $id]);
            }

            // Was in einem abgewählten Behältnis lag, ist wieder „ohne Behältnis“.
            $this->entries($trip, $user)->whereNotNull('container_id')->whereNotIn('container_id', $ids)->update(['container_id' => null]);

            $this->applyDefaults($trip, $user);
        });
    }

    /** Einträge ohne Behältnis landen im Standardplatz ihres Gegenstands, wenn der mitkommt. */
    public function applyDefaults(Trip $trip, User $user): void
    {
        $selected = $this->selectedIds($trip, $user);
        if ($selected->isEmpty()) {
            return;
        }

        $this->entries($trip, $user)
            ->whereNull('container_id')
            ->whereNotNull('item_id')
            ->with('item')
            ->get()
            ->each(function (PacklistEntry $entry) use ($selected) {
                if ($selected->contains($entry->item?->default_container_id)) {
                    $entry->update(['container_id' => $entry->item->default_container_id]);
                }
            });
    }

    /**
     * Mehrere Einträge auf einmal zuordnen; null nimmt sie aus dem Behältnis.
     *
     * @param  list<int>  $entryIds
     * @return int Anzahl zugeordneter Einträge
     */
    public function assign(Trip $trip, User $user, array $entryIds, ?int $containerId): int
    {
        if ($containerId !== null && ! $this->selectedIds($trip, $user)->contains($containerId)) {
            return 0;
        }

        return $this->entries($trip, $user)->whereIn('id', $entryIds)->update(['container_id' => $containerId]);
    }

    /**
     * Inhalt und Gewicht je Behältnis für die Ansicht „nach Behältnis“.
     *
     * @param  Collection<int, PacklistEntry>  $entries  sichtbare Einträge mit geladenem item
     * @param  array<int, int>  $worn  bei der Anreise getragen (item_id => Stück), zählt nicht mit
     * @return Collection<int, array{container: Container, entries: Collection, weight: int, over: bool}>
     */
    public function summary(Trip $trip, User $user, Collection $entries, array $worn = []): Collection
    {
        return $trip->containersFor($user)->orderBy('name')->get()->map(function (Container $container) use ($entries, $worn) {
            $inside = $entries->where('container_id', $container->id)->values();
            $weight = ($container->empty_weight_g ?? 0) + $inside->sum(fn (PacklistEntry $e) => $e->weight() - self::wornWeight($e, $worn));

            return [
                'container' => $container,
                'entries' => $inside,
                'weight' => $weight,
                'over' => $container->max_weight_g !== null && $weight > $container->max_weight_g,
            ];
        });
    }

    /**
     * Gesamtgewicht des Gepäcks: alle sichtbaren Einträge plus Leergewicht der gewählten
     * Behältnisse, abzüglich der Kleidung, die man bei der Anreise trägt („worn“).
     * „missing“ zählt Einträge ohne gepflegtes Gewicht – die Summe ist dann eine Untergrenze.
     *
     * @param  Collection<int, PacklistEntry>  $entries  sichtbare Einträge mit geladenem item
     * @param  Collection<int, array{container: Container}>  $summary
     * @param  array<int, int>  $worn  item_id => getragene Stückzahl
     * @return array{total: int, containers: int, worn: int, missing: int}
     */
    public static function totalWeight(Collection $entries, Collection $summary, array $worn = []): array
    {
        $containers = (int) $summary->sum(fn ($row) => $row['container']->empty_weight_g ?? 0);
        $wornWeight = (int) $entries->sum(fn (PacklistEntry $e) => self::wornWeight($e, $worn));

        return [
            'total' => (int) $entries->sum(fn (PacklistEntry $e) => $e->weight()) - $wornWeight + $containers,
            'containers' => $containers,
            'worn' => $wornWeight,
            'missing' => $entries->filter(fn (PacklistEntry $e) => $e->item?->weight_g === null)->count(),
        ];
    }

    /** Gewicht der Stücke eines Eintrags, die man bei der Anreise trägt. */
    private static function wornWeight(PacklistEntry $entry, array $worn): int
    {
        $pieces = $worn[$entry->item_id] ?? 0;

        return $pieces > 0 ? min($pieces, $entry->quantity) * ($entry->item?->weight_g ?? 0) : 0;
    }

    /**
     * „Du brauchst: 1 Koffer, 2 Boxen“ – nur Behältnisse, in denen auch etwas liegt.
     *
     * @param  Collection<int, array{container: Container, entries: Collection}>  $summary
     */
    public static function neededLabel(Collection $summary): ?string
    {
        $counts = $summary->filter(fn ($row) => $row['entries']->isNotEmpty())
            ->countBy(fn ($row) => $row['container']->kind->value)
            ->sortKeys();

        if ($counts->isEmpty()) {
            return null;
        }

        return $counts->map(function (int $count, string $kind) {
            $kind = ContainerKind::from($kind);

            return $count.' '.($count === 1 ? $kind->label() : $kind->plural());
        })->join(', ', ' und ');
    }

    /** @return Collection<int, int> */
    public function selectedIds(Trip $trip, User $user): Collection
    {
        return DB::table('trip_containers')->where(['trip_id' => $trip->id, 'user_id' => $user->id])->pluck('container_id');
    }

    private function entries(Trip $trip, User $user)
    {
        return PacklistEntry::where(['trip_id' => $trip->id, 'user_id' => $user->id]);
    }
}
