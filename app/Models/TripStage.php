<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Ort einer Reise an einem bestimmten Tag (Etappe). */
class TripStage extends Model
{
    protected $fillable = ['trip_id', 'date', 'location'];

    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }

    /** @return BelongsTo<Trip, $this> */
    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }
}
