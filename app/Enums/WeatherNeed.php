<?php

namespace App\Enums;

use App\Support\DayWeather;
use App\Support\RainGear;

/**
 * Wogegen ein Gegenstand schützt. Ist es angesagt und nichts davon auf der Liste
 * oder im Outfit, weist die Packliste darauf hin.
 */
enum WeatherNeed: string
{
    case Rain = 'rain';
    case Cold = 'cold';
    case Sun = 'sun';

    /** Kalt: Tiefstwert bis 5 °C. Sonnig: ab 25 °C bei höchstens leichter Bewölkung. */
    public const COLD_MAX_MIN = 5;

    public const SUN_MIN_MAX = 25;

    /** Spalte am Gegenstand */
    public function column(): string
    {
        return $this->value.'_protection';
    }

    /** Vorbelegung nach dem Namen; gilt auch in JavaScript. */
    public function pattern(): string
    {
        return match ($this) {
            self::Rain => RainGear::PATTERN,
            self::Cold => 'mütze|handschuh|fäustling|\bschal\b|daune|fleece|thermo|winter|\bwoll|merino|stirnband|beanie|parka|ohrenwärmer|wärmflasche',
            self::Sun => 'sonnen(?!blume)|after.?sun|\blsf\b|\bspf\b|\buv\b|basecap|\bcap\b|\bhut\b|sun(?:screen|block)',
        };
    }

    public function matches(string $name): bool
    {
        return (bool) preg_match('/'.$this->pattern().'/iu', $name);
    }

    public function isDue(DayWeather $weather): bool
    {
        return match ($this) {
            self::Rain => $weather->isRainy(),
            self::Cold => $weather->min <= self::COLD_MAX_MIN,
            self::Sun => $weather->max >= self::SUN_MIN_MAX && $weather->code <= 2,
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Rain => 'cloud-rain',
            self::Cold => 'snowflake',
            self::Sun => 'sun',
        };
    }

    /** Häkchen im Formular */
    public function label(): string
    {
        return match ($this) {
            self::Rain => 'Schützt vor Regen',
            self::Cold => 'Hält warm',
            self::Sun => 'Schützt vor Sonne',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Rain => 'Z. B. Regenjacke, Schirm oder Softshell. Ist Regen angesagt und nichts davon dabei, weist die Packliste darauf hin.',
            self::Cold => 'Z. B. Mütze, Handschuhe oder Daunenjacke. Wird es kalt und nichts davon ist dabei, weist die Packliste darauf hin.',
            self::Sun => 'Z. B. Sonnencreme, Sonnenbrille oder Kappe. Wird es sonnig und heiß und nichts davon ist dabei, weist die Packliste darauf hin.',
        };
    }

    /** Kurzform im Katalog */
    public function badge(): string
    {
        return match ($this) {
            self::Rain => 'Regenschutz',
            self::Cold => 'Wärme',
            self::Sun => 'Sonnenschutz',
        };
    }

    public function badgeIcon(): string
    {
        return match ($this) {
            self::Rain => 'umbrella',
            self::Cold => 'snowflake',
            self::Sun => 'sun',
        };
    }

    /** Überschrift des Hinweises, z. B. „Regen angesagt: Mi, 13. Mai“ */
    public function headline(): string
    {
        return match ($this) {
            self::Rain => 'Regen angesagt',
            self::Cold => 'Kalt',
            self::Sun => 'Sonnig und heiß',
        };
    }

    public function hint(): string
    {
        return match ($this) {
            self::Rain => 'Auf deiner Liste steht noch nichts gegen Regen – Regenjacke, Schirm oder Poncho?',
            self::Cold => 'Auf deiner Liste steht noch nichts, was warm hält – Mütze, Handschuhe oder eine warme Jacke?',
            self::Sun => 'Auf deiner Liste steht noch kein Sonnenschutz – Sonnencreme, Sonnenbrille oder Kappe?',
        };
    }

    /** Vorschlag, wenn im Katalog nichts passt: [Name, Karte] */
    public function fallback(): array
    {
        return match ($this) {
            self::Rain => ['Regenjacke', PacklistGroup::Clothing],
            self::Cold => ['Mütze', PacklistGroup::Clothing],
            self::Sun => ['Sonnencreme', PacklistGroup::Toiletries],
        };
    }
}
