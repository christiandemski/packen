<?php

namespace App\Services;

use App\Enums\PacklistGroup;
use App\Models\PacklistEntry;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Packliste zum Mitnehmen: als Druckansicht (der Browser macht daraus ein PDF) oder als
 * Text für Notiz-Apps – „☐ Socken“ für Apple Notizen/Google Notizen, „- [ ] Socken“
 * als Markdown für Apps wie Obsidian, Bear oder Notion.
 */
class PacklistExport
{
    public const FORMATS = ['txt', 'md'];

    /**
     * Abschnitte der Liste: nach Gruppe (wie in der App) oder nach Behältnis.
     *
     * @return Collection<int, array{title: string, entries: Collection<int, PacklistEntry>}>
     */
    public function sections(Trip $trip, User $owner, bool $byContainer = false, bool $onlyOpen = false): Collection
    {
        $entries = $trip->entries()->with(['item', 'container', 'groupItem'])
            ->where('user_id', $owner->id)->visible()
            ->when($onlyOpen, fn ($q) => $q->where('packed', false))
            ->get()
            ->sortBy(fn (PacklistEntry $e) => mb_strtolower($e->name()));

        if ($byContainer) {
            return $entries
                ->groupBy(fn (PacklistEntry $e) => $e->container?->name ?? '')
                ->sortKeys(SORT_NATURAL | SORT_FLAG_CASE)
                ->sortBy(fn ($group, $name) => $name === '' ? 1 : 0) // „Ohne Behältnis“ ans Ende
                ->map(fn ($group, $name) => ['title' => $name === '' ? 'Ohne Behältnis' : $name, 'entries' => $group->values()])
                ->values();
        }

        return $entries
            ->groupBy(fn (PacklistEntry $e) => $e->packlistGroup()->value)
            ->sortBy(fn ($group, $key) => PacklistGroup::from($key)->position())
            ->map(fn ($group, $key) => ['title' => PacklistGroup::from($key)->label(), 'entries' => $group->values()])
            ->values();
    }

    /** Zusatz hinter dem Namen, z. B. „USB-C · in: Rucksack“. */
    public static function details(PacklistEntry $entry, bool $byContainer = false): string
    {
        return collect([
            $entry->note,
            ! $byContainer && $entry->container ? 'in: '.$entry->container->name : null,
        ])->filter()->join(' · ');
    }

    public static function label(PacklistEntry $entry): string
    {
        return ($entry->quantity > 1 ? $entry->quantity.'× ' : '').$entry->name();
    }

    /** @param bool $withName „Packliste von Anna“ – wenn jemand die freigegebene Liste einer anderen Person exportiert */
    public function text(Trip $trip, User $owner, string $format, bool $byContainer = false, bool $onlyOpen = false, bool $withName = false): string
    {
        $md = $format === 'md';
        $title = self::title($trip, $owner, $withName);
        $subtitle = self::subtitle($trip);

        $lines = $md ? ['# '.$title, '', '_'.$subtitle.'_'] : [$title, $subtitle];

        foreach ($this->sections($trip, $owner, $byContainer, $onlyOpen) as $section) {
            $lines[] = '';
            $lines[] = $md ? '## '.$section['title'] : mb_strtoupper($section['title']);
            foreach ($section['entries'] as $entry) {
                $box = $md ? ($entry->packed ? '- [x] ' : '- [ ] ') : ($entry->packed ? '☑ ' : '☐ ');
                $details = self::details($entry, $byContainer);
                $lines[] = $box.self::label($entry).($details !== '' ? ' – '.$details : '');
            }
        }

        return implode("\n", $lines)."\n";
    }

    public static function title(Trip $trip, User $owner, bool $withName): string
    {
        return $trip->name.' – '.($withName ? 'Packliste von '.$owner->name : 'Packliste');
    }

    public static function subtitle(Trip $trip): string
    {
        return $trip->dateRangeLabel().' · '.$trip->durationLabel().($trip->location ? ' · '.$trip->location : '');
    }

    public function filename(Trip $trip, string $format): string
    {
        return 'Packliste '.preg_replace('/[^\pL\pN \-_.]/u', '', $trip->name).'.'.$format;
    }
}
