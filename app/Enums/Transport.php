<?php

namespace App\Enums;

enum Transport: string
{
    case Car = 'car';
    case Train = 'train';
    case Plane = 'plane';
    case Bus = 'bus';
    case Bike = 'bike';
    case OnFoot = 'on_foot';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Car => 'Auto',
            self::Train => 'Bahn',
            self::Plane => 'Flugzeug',
            self::Bus => 'Bus',
            self::Bike => 'Fahrrad',
            self::OnFoot => 'Zu Fuß',
            self::Other => 'Sonstiges',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Car => 'car',
            self::Train => 'train',
            self::Plane => 'plane',
            self::Bus => 'bus',
            self::Bike => 'bike',
            self::OnFoot => 'footprints',
            self::Other => 'compass',
        };
    }
}
