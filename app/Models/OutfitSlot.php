<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class OutfitSlot extends Model
{
    /** Vorschläge für weitere Slots; eigene Namen sind auch möglich. */
    public const SUGGESTIONS = ['Abend', 'Sport', 'Anreise', 'Nacht'];

    public const DEFAULT_NAME = 'Tag';

    protected $fillable = ['outfit_day_id', 'name', 'position'];

    /** @return BelongsTo<OutfitDay, $this> */
    public function day(): BelongsTo
    {
        return $this->belongsTo(OutfitDay::class, 'outfit_day_id');
    }

    /** @return BelongsToMany<Item, $this> */
    public function items(): BelongsToMany
    {
        return $this->belongsToMany(Item::class, 'outfit_slot_items')->orderBy('name');
    }
}
