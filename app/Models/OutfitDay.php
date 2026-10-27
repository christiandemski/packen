<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OutfitDay extends Model
{
    protected $fillable = ['trip_id', 'user_id', 'date', 'title', 'laundry_day'];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'laundry_day' => 'boolean',
        ];
    }

    /** @return BelongsTo<Trip, $this> */
    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    /** @return HasMany<OutfitSlot, $this> */
    public function slots(): HasMany
    {
        return $this->hasMany(OutfitSlot::class)->orderBy('position')->orderBy('id');
    }

    /** z. B. „Di, 13. Mai“ */
    public function label(): string
    {
        return $this->date->locale('de')->minDayName.', '.$this->date->translatedFormat('j. F');
    }
}
