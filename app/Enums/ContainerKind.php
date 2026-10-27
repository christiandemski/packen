<?php

namespace App\Enums;

enum ContainerKind: string
{
    case Suitcase = 'suitcase';
    case Backpack = 'backpack';
    case Box = 'box';
    case Bag = 'bag';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Suitcase => 'Koffer',
            self::Backpack => 'Rucksack',
            self::Box => 'Box',
            self::Bag => 'Tasche',
            self::Other => 'Sonstiges',
        };
    }

    /** Für „Du brauchst: 1 Koffer, 2 Boxen“ */
    public function plural(): string
    {
        return match ($this) {
            self::Suitcase => 'Koffer',
            self::Backpack => 'Rucksäcke',
            self::Box => 'Boxen',
            self::Bag => 'Taschen',
            self::Other => 'Sonstige',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Suitcase => 'luggage',
            self::Backpack => 'backpack',
            self::Box => 'package',
            self::Bag => 'shopping-bag',
            self::Other => 'package',
        };
    }
}
