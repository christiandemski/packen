<?php

namespace App\Support;

/**
 * Regenschutz am Namen erkennen (Regenjacke, Schirm, Poncho …) – für freie Einträge und
 * als Vorbelegung neuer Gegenstände. Hinweise und die anderen Wetterarten: App\Enums\WeatherNeed.
 */
final class RainGear
{
    /** Gilt auch in JavaScript (Vorbelegung im Formular). */
    public const PATTERN = 'regen(?!erat)|schirm|poncho|hardshell|gore-?tex|wasserdicht|friesennerz|\bcape\b|\brain';

    public static function matches(string $name): bool
    {
        return (bool) preg_match('/'.self::PATTERN.'/iu', $name);
    }
}
