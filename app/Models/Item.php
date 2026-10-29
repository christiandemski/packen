<?php

namespace App\Models;

use App\Enums\EntrySource;
use App\Enums\ItemCategory;
use App\Enums\PacklistGroup;
use App\Enums\WeatherNeed;
use App\Models\Concerns\OwnedByUser;
use App\Support\Group;
use Database\Factories\ItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Item extends Model
{
    /** @use HasFactory<ItemFactory> */
    use HasFactory, OwnedByUser;

    protected $fillable = ['owner_id', 'name', 'category', 'category_id', 'weight_g', 'per_day', 'pack_last', 'rain_protection', 'cold_protection', 'sun_protection', 'default_container_id', 'notes'];

    protected function casts(): array
    {
        return [
            'category' => ItemCategory::class,
            'weight_g' => 'integer',
            'is_clothing' => 'boolean',
            'per_day' => 'boolean',
            'pack_last' => 'boolean',
            'rain_protection' => 'boolean',
            'cold_protection' => 'boolean',
            'sun_protection' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // Ohne ausdrückliche Angabe (Outfit-Planer, Start-Vorlagen) nach dem Namen vorbelegen.
        static::creating(function (Item $item) {
            foreach (WeatherNeed::cases() as $need) {
                if (! array_key_exists($need->column(), $item->getAttributes())) {
                    $item->{$need->column()} = $need->matches($item->name);
                }
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
                'group' => $item->packlistGroup()->key,
                'source' => EntrySource::Manual,
            ]);
        });
    }

    /** Eigene Kategorie (statt der festen) */
    public function customCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    /** Überschrift im Katalog und in Vorlagen */
    public function categoryGroup(): Group
    {
        return $this->category_id && $this->customCategory
            ? Group::custom($this->customCategory)
            : Group::category($this->category);
    }

    /** Karte in der Packliste */
    public function packlistGroup(): Group
    {
        return match (true) {
            $this->pack_last => Group::packlist(PacklistGroup::DepartureDay),
            $this->category_id && $this->customCategory !== null => Group::custom($this->customCategory, packlist: true),
            default => Group::packlist(PacklistGroup::forCategory($this->category)),
        };
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

    /** @return list<WeatherNeed> */
    public function weatherNeeds(): array
    {
        return array_values(array_filter(WeatherNeed::cases(), fn (WeatherNeed $need) => (bool) $this->{$need->column()}));
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
