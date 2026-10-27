<?php

namespace App\Enums;

enum Accommodation: string
{
    case Hotel = 'hotel';
    case Apartment = 'apartment';
    case Hostel = 'hostel';
    case Camping = 'camping';
    case Hut = 'hut';
    case Friends = 'friends';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Hotel => 'Hotel',
            self::Apartment => 'Ferienwohnung',
            self::Hostel => 'Hostel',
            self::Camping => 'Camping',
            self::Hut => 'Hütte',
            self::Friends => 'Bei Freunden',
            self::Other => 'Sonstiges',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Hotel => 'building',
            self::Apartment => 'house',
            self::Hostel => 'bed',
            self::Camping => 'tent',
            self::Hut => 'house',
            self::Friends => 'users',
            self::Other => 'bed',
        };
    }
}
