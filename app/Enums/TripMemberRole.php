<?php

namespace App\Enums;

enum TripMemberRole: string
{
    case Owner = 'owner';
    case Member = 'member';
}
