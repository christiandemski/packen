<?php

namespace App\Enums;

enum Transport: string
{
    case Plane = 'plane';
    case Train = 'train';
    case Car = 'car';
    case RentalCar = 'rental_car';
    case Bus = 'bus';
    case Ferry = 'ferry';
    case Bike = 'bike';
    case OnFoot = 'on_foot';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Plane => 'Flugzeug',
            self::Train => 'Bahn',
            self::Car => 'Auto',
            self::RentalCar => 'Mietwagen',
            self::Bus => 'Bus',
            self::Ferry => 'Fähre',
            self::Bike => 'Fahrrad',
            self::OnFoot => 'Zu Fuß',
            self::Other => 'Sonstiges',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Plane => 'plane',
            self::Train => 'train',
            self::Car, self::RentalCar => 'car',
            self::Bus => 'bus',
            self::Ferry => 'ship',
            self::Bike => 'bike',
            self::OnFoot => 'footprints',
            self::Other => 'compass',
        };
    }
}
