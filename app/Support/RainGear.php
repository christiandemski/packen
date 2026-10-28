<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Erkennt Regenschutz am Namen (Regenjacke, Schirm, Poncho, Hardshell …) und meldet
 * Regentage, für die nichts davon auf der Liste oder im Outfit steht.
 */
final class RainGear
{
    private const PATTERN = '/regen(?!erat)|schirm|poncho|hardshell|gore-?tex|wasserdicht|friesennerz|\bcape\b|\brain/iu';

    public static function matches(string $name): bool
    {
        return (bool) preg_match(self::PATTERN, $name);
    }

    /**
     * Regentage ohne Regenschutz, z. B. „Mi, 13. Mai“; leer, wenn alles passt.
     *
     * @param  array<string, DayWeather>  $days  Datum (Y-m-d) => Wetter
     * @param  iterable<string>  $names  was auf der Liste bzw. in den Outfits steht
     * @return list<string>
     */
    public static function missingFor(array $days, iterable $names): array
    {
        foreach ($names as $name) {
            if (self::matches($name)) {
                return [];
            }
        }

        return collect($days)
            ->filter(fn (DayWeather $weather) => $weather->isRainy())
            ->keys()
            ->map(fn (string $date) => ($d = Carbon::parse($date))->locale('de')->minDayName.', '.$d->translatedFormat('j. F'))
            ->values()
            ->all();
    }
}
