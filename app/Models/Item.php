<?php

namespace App\Models;

use App\Enums\EntrySource;
use App\Enums\ItemCategory;
use App\Enums\PacklistGroup;
use App\Models\Concerns\OwnedByUser;
use App\Support\RainGear;
use Database\Factories\ItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Item extends Model
{
    /** @use HasFactory<ItemFactory> */
    use HasFactory, OwnedByUser;

    protected $fillable = ['owner_id', 'name', 'category', 'weight_g', 'per_day', 'pack_last', 'rain_protection', 'default_container_id', 'notes'];

    protected function casts(): array
    {
        return [
            'category' => ItemCategory::class,
            'weight_g' => 'integer',
            'is_clothing' => 'boolean',
            'per_day' => 'boolean',
            'pack_last' => 'boolean',
            'rain_protection' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // Ohne ausdrückliche Angabe (Outfit-Planer, Start-Vorlagen) nach dem Namen vorbelegen.
        static::creating(function (Item $item) {
            if (! array_key_exists('rain_protection', $item->getAttributes())) {
                $item->rain_protection = RainGear::matches($item->name);
            }
        });

        // „Kleidung“ ergibt sich aus der Kategorie – so kann es nie widersprüchlich werden.
        static::saving(function (Item $item) {
            $item->is_clothing = $item->category->isClothing();
        });

        // Packlisten behalten den Namen, auch wenn der Gegenstand aus dem Katalog verschwindet.
        static::deleting(function (Item $item) {
            PacklistEntry::where('item_id', $item->id)->update([
                'custom_name' => $item->name,
                'group' => ($item->pack_last ? PacklistGroup::DepartureDay : PacklistGroup::forCategory($item->category))->value,
                'source' => EntrySource::Manual,
            ]);
        });
    }

    /** Standardplatz, z. B. Kocher → „Eurobox Küche“ */
    public function defaultContainer(): BelongsTo
    {
        return $this->belongsTo(Container::class, 'default_container_id');
    }

    /** @return BelongsToMany<Template, $this> */
    public function templates(): BelongsToMany
    {
        return $this->belongsToMany(Template::class, 'template_items')->withPivot('quantity');
    }

    public function weightLabel(): ?string
    {
        return match (true) {
            $this->weight_g === null => null,
            $this->weight_g >= 1000 => number_format($this->weight_g / 1000, 2, ',', '.').' kg',
            default => $this->weight_g.' g',
        };
    }
}
