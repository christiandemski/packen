<?php

namespace App\Support;

use App\Enums\WeatherNeed;
use App\Models\Item;
use App\Models\PacklistEntry;
use App\Models\User;
use Illuminate\Support\Carbon;

/** Hinweise der Packliste: Wetter angesagt, aber nichts dagegen dabei. */
final class WeatherGear
{
    /**
     * @param  iterable<PacklistEntry>  $entries  Einträge der Packliste
     * @param  iterable<Item>  $items  Teile aus den Outfits
     */
    public static function covered(WeatherNeed $need, iterable $entries, iterable $items): bool
    {
        $column = $need->column();

        foreach ($entries as $entry) {
            if ($entry->item ? $entry->item->{$column} : $need->matches($entry->name())) {
                return true;
            }
        }

        foreach ($items as $item) {
            if ($item->{$column}) {
                return true;
            }
        }

        return false;
    }

    /**
     * Betroffene Tage, z. B. „Mi, 13. Mai“.
     *
     * @param  array<string, DayWeather>  $days  Datum (Y-m-d) => Wetter
     * @return list<string>
     */
    public static function days(WeatherNeed $need, array $days): array
    {
        return collect($days)
            ->filter(fn (DayWeather $weather) => $need->isDue($weather))
            ->keys()
            ->map(fn (string $date) => ($d = Carbon::parse($date))->locale('de')->minDayName.', '.$d->translatedFormat('j. F'))
            ->values()
            ->all();
    }

    /**
     * Alle offenen Hinweise samt Vorschlag aus dem eigenen Katalog.
     *
     * @param  iterable<PacklistEntry>  $entries
     * @param  iterable<Item>  $outfitItems
     * @param  array<string, DayWeather>  $weatherDays
     * @return list<array{need: WeatherNeed, days: list<string>, gear: ?Item}>
     */
    public static function hints(User $user, iterable $entries, iterable $outfitItems, array $weatherDays): array
    {
        $hints = [];

        foreach (WeatherNeed::cases() as $need) {
            $days = self::days($need, $weatherDays);
            if ($days === [] || self::covered($need, $entries, $outfitItems)) {
                continue;
            }

            $hints[] = [
                'need' => $need,
                'days' => $days,
                'gear' => Item::accessibleBy($user)->where($need->column(), true)->orderBy('name')->first(),
            ];
        }

        return $hints;
    }
}
