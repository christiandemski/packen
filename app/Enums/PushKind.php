<?php

namespace App\Enums;

/** Arten von Push-Nachrichten, die jede Person einzeln ein- und ausschalten kann. */
enum PushKind: string
{
    case Group = 'group';
    case Eve = 'eve';
    case Departure = 'departure';
    case Review = 'review';

    public function label(): string
    {
        return match ($this) {
            self::Group => 'Hinweise aus der Gruppe',
            self::Eve => 'Am Vorabend',
            self::Departure => 'Am Abfahrtstag',
            self::Review => 'Rückblick nach der Reise',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Group => 'Wenn jemand fragt („Hat jemand einen Föhn?“) oder etwas für dich mitbringt.',
            self::Eve => 'Um 18 Uhr vor der Abreise: was auf der Packliste noch offen ist.',
            self::Departure => 'Um 7 Uhr am Abreisetag, wenn aus „Am Abfahrtstag“ oder den Aufgaben noch etwas offen ist.',
            self::Review => 'Um 10 Uhr am Tag nach der Reise: Was hat gefehlt, was war zu viel?',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Group => 'users',
            self::Eve => 'calendar',
            self::Departure => 'clock',
            self::Review => 'history',
        };
    }

    /** Ab wann (Ortszeit) die Erinnerung rausgeht; Gruppen-Hinweise sofort. */
    public function hour(): ?int
    {
        return match ($this) {
            self::Group => null,
            self::Eve => 18,
            self::Departure => 7,
            self::Review => 10,
        };
    }
}
