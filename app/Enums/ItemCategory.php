<?php

namespace App\Enums;

/** Feste Kategorien, damit Packliste und Outfit-Tabelle sauber gruppieren können. */
enum ItemCategory: string
{
    // Kleidung (Reihenfolge = Zeilen in der Outfit-Wochenübersicht)
    case Tops = 'tops';
    case Bottoms = 'bottoms';
    case Underwear = 'underwear';
    case Outerwear = 'outerwear';
    case Sportswear = 'sportswear';
    case Shoes = 'shoes';
    case Accessories = 'accessories';

    // Alles andere
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
            self::Tops => 'Oberteile',
            self::Bottoms => 'Hosen & Röcke',
            self::Underwear => 'Unterwäsche & Socken',
            self::Outerwear => 'Jacken & Pullover',
            self::Sportswear => 'Sport- & Badekleidung',
            self::Shoes => 'Schuhe',
            self::Accessories => 'Accessoires',
            self::Toiletries => 'Hygiene',
            self::Health => 'Gesundheit',
            self::Electronics => 'Elektronik',
            self::Documents => 'Dokumente & Geld',
            self::Gear => 'Ausrüstung',
            self::Sleep => 'Schlafen',
            self::Food => 'Essen & Küche',
            self::Misc => 'Sonstiges',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Tops, self::Bottoms, self::Underwear => 'shirt',
            self::Outerwear => 'layers',
            self::Sportswear => 'dumbbell',
            self::Shoes => 'footprints',
            self::Accessories => 'glasses',
            self::Toiletries => 'droplet',
            self::Health => 'pill',
            self::Electronics => 'plug',
            self::Documents => 'wallet',
            self::Gear => 'backpack',
            self::Sleep => 'bed',
            self::Food => 'utensils',
            self::Misc => 'package',
        };
    }

    public function isClothing(): bool
    {
        return in_array($this, self::clothing(), true);
    }

    /** @return list<self> */
    public static function clothing(): array
    {
        return [self::Tops, self::Bottoms, self::Underwear, self::Outerwear, self::Sportswear, self::Shoes, self::Accessories];
    }

    public function position(): int
    {
        return array_search($this, self::cases(), true);
    }
}
