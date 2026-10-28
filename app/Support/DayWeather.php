<?php

namespace App\Support;

/** Wetter eines Tages, Werte in °C bzw. Prozent. */
final readonly class DayWeather
{
    public const RAIN_THRESHOLD = 50;

    public function __construct(
        public int $code,
        public int $min,
        public int $max,
        public ?int $rainChance,
    ) {}

    /** Bezeichnung und Icon nach WMO-Wettercode. */
    public function label(): string
    {
        return $this->describe()[0];
    }

    public function icon(): string
    {
        return $this->describe()[1];
    }

    /** z. B. „14–21 °C · Regen 40 %“ */
    public function summary(): string
    {
        $text = $this->min.'–'.$this->max.' °C';

        if ($this->rainChance !== null && $this->rainChance >= 20) {
            $text .= ' · Regen '.$this->rainChance.' %';
        }

        return $text;
    }

    /** Regen lohnt einen Hinweis: ab 50 % Wahrscheinlichkeit, ohne Angabe nach Wettercode. */
    public function isRainy(): bool
    {
        if ($this->rainChance !== null) {
            return $this->rainChance >= self::RAIN_THRESHOLD;
        }

        return in_array($this->icon(), ['cloud-drizzle', 'cloud-rain', 'cloud-lightning'], true);
    }

    /** @return array{string, string} */
    private function describe(): array
    {
        return match (true) {
            $this->code === 0 => ['Sonnig', 'sun'],
            $this->code === 1 => ['Überwiegend sonnig', 'sun'],
            $this->code === 2 => ['Teils bewölkt', 'cloud-sun'],
            $this->code === 3 => ['Bedeckt', 'cloud'],
            in_array($this->code, [45, 48], true) => ['Nebel', 'cloud-fog'],
            $this->code >= 51 && $this->code <= 57 => ['Nieselregen', 'cloud-drizzle'],
            in_array($this->code, [66, 67], true) => ['Gefrierender Regen', 'cloud-rain'],
            $this->code >= 61 && $this->code <= 65 => ['Regen', 'cloud-rain'],
            $this->code >= 80 && $this->code <= 82 => ['Regenschauer', 'cloud-rain'],
            $this->code >= 71 && $this->code <= 77 => ['Schnee', 'cloud-snow'],
            in_array($this->code, [85, 86], true) => ['Schneeschauer', 'cloud-snow'],
            $this->code >= 95 => ['Gewitter', 'cloud-lightning'],
            default => ['Wechselhaft', 'cloud'],
        };
    }
}
