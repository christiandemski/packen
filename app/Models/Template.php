<?php

namespace App\Models;

use App\Models\Concerns\OwnedByUser;
use Database\Factories\TemplateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Template extends Model
{
    /** @use HasFactory<TemplateFactory> */
    use HasFactory, OwnedByUser;

    protected $fillable = ['owner_id', 'name', 'description'];

    /** @return BelongsToMany<Item, $this> */
    public function items(): BelongsToMany
    {
        return $this->belongsToMany(Item::class, 'template_items')->withPivot('quantity');
    }

    /** Gesamtgewicht in Gramm, soweit Gewichte gepflegt sind. */
    public function totalWeight(): int
    {
        return (int) $this->items->sum(fn (Item $item) => ($item->weight_g ?? 0) * $item->pivot->quantity);
    }
}
