<?php

namespace App\Support;

use App\Models\Item;
use App\Models\PacklistEntry;
use Illuminate\Support\Carbon;

/**
 * Regenschutz: Gegenstände aus dem Katalog tragen das Häkchen „schützt vor Regen“;
 * am Namen (Regenjacke, Schirm, Poncho …) erkannt wird nur, was kein Häkchen haben
 * kann – freie Einträge – und als Vorbelegung für neue Gegenstände.
 */
final class RainGear
{
    /** Gilt auch in JavaScript (Vorbelegung im Formular). */
    public const PATTERN = 'regen(?!erat)|schirm|poncho|hardshell|gore-?tex|wasserdicht|friesennerz|\bcape\b|\brain';

    public static function matches(string $name): bool
    {
        return (bool) preg_match('/'.self::PATTERN.'/iu', $name);
    }

    /**
     * @param  iterable<PacklistEntry>  $entries  Einträge der Packliste
     * @param  iterable<Item>  $items  Teile aus den Outfits
     */
    public static function covered(iterable $entries, iterable $items): bool
    {
        foreach ($entries as $entry) {
            if ($entry->item ? $entry->item->rain_protection : self::matches($entry->name())) {
                return true;
            }
        }

        foreach ($items as $item) {
            if ($item->rain_protection) {
                return true;
            }
        }

        return false;
    }

    /**
     * Regentage, z. B. „Mi, 13. Mai“.
     *
     * @param  array<string, DayWeather>  $days  Datum (Y-m-d) => Wetter
     * @return list<string>
     */
    public static function rainyDays(array $days): array
    {
        return collect($days)
            ->filter(fn (DayWeather $weather) => $weather->isRainy())
            ->keys()
            ->map(fn (string $date) => ($d = Carbon::parse($date))->locale('de')->minDayName.', '.$d->translatedFormat('j. F'))
            ->values()
            ->all();
    }
}
