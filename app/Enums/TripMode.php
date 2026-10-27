<?php

namespace App\Enums;

enum TripMode: string
{
    case Solo = 'solo';
    case Group = 'group';

    public function label(): string
    {
        return match ($this) {
            self::Solo => 'Privat',
            self::Group => 'Gemeinsam',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Solo => 'lock',
            self::Group => 'users',
        };
    }
}
