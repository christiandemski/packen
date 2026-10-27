<?php

namespace App\Models;

use App\Enums\TripMemberRole;
use Illuminate\Database\Eloquent\Relations\Pivot;

class TripMember extends Pivot
{
    protected $table = 'trip_members';

    public $incrementing = true;

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'role' => TripMemberRole::class,
            'outfits_shared' => 'boolean',
            'joined_at' => 'datetime',
        ];
    }
}
