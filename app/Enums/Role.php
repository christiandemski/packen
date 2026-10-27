<?php

namespace App\Enums;

enum Role: string
{
    case Admin = 'admin';
    case Friend = 'friend';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::Friend => 'Freund',
        };
    }
}
