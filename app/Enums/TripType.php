<?php

namespace App\Enums;

enum TripType: string
{
    case Citytrip = 'citytrip';
    case Beach = 'beach';
    case Hiking = 'hiking';
    case Bikepacking = 'bikepacking';
    case RunningEvent = 'running_event';
    case Winter = 'winter';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Citytrip => 'Städtetrip',
            self::Beach => 'Strand',
            self::Hiking => 'Wandern',
            self::Bikepacking => 'Bikepacking',
            self::RunningEvent => 'Laufevent',
            self::Winter => 'Winter',
            self::Other => 'Sonstiges',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Citytrip => 'building',
            self::Beach => 'umbrella',
            self::Hiking => 'mountain',
            self::Bikepacking => 'bike',
            self::RunningEvent => 'trophy',
            self::Winter => 'snowflake',
            self::Other => 'compass',
        };
    }

    /** CSS-Verlauf für Kacheln ohne eigenes Bild. */
    public function gradient(): string
    {
        return match ($this) {
            self::Citytrip => 'linear-gradient(135deg, #17211E 0%, #0E6B5E 100%)',
            self::Beach => 'linear-gradient(135deg, #0A5248 0%, #2B8A7C 100%)',
            self::Hiking => 'linear-gradient(135deg, #263D30 0%, #55704A 100%)',
            self::Bikepacking => 'linear-gradient(135deg, #5E2A0E 0%, #B04F18 100%)',
            self::RunningEvent => 'linear-gradient(135deg, #33284F 0%, #5F4A8C 100%)',
            self::Winter => 'linear-gradient(135deg, #1F3A54 0%, #47729A 100%)',
            self::Other => 'linear-gradient(135deg, #17211E 0%, #4A5652 100%)',
        };
    }
}
