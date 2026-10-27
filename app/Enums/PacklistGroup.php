<?php

namespace App\Enums;

/**
 * Karten der Packliste in fester Reihenfolge. „Am Abfahrtstag“ steht immer oben,
 * alle Kleidungskategorien teilen sich die Karte „Kleidung“.
 */
enum PacklistGroup: string
{
    case DepartureDay = 'departure_day';
    case Clothing = 'clothing';
    case Toiletries = 'toiletries';
    case Health = 'health';
    case Electronics = 'electronics';
    case Documents = 'documents';
    case Gear = 'gear';
    case Sleep = 'sleep';
    case Food = 'food';
    case Misc = 'misc';

    public function label(): string
    {
        return match ($this) {
            self::DepartureDay => 'Am Abfahrtstag',
            self::Clothing => 'Kleidung',
            default => ItemCategory::from($this->value)->label(),
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::DepartureDay => 'clock',
            self::Clothing => 'shirt',
            default => ItemCategory::from($this->value)->icon(),
        };
    }

    public static function forCategory(ItemCategory $category): self
    {
        return $category->isClothing() ? self::Clothing : self::from($category->value);
    }

    public function position(): int
    {
        return array_search($this, self::cases(), true);
    }
}
