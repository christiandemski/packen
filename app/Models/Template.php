<?php

namespace App\Models;

use App\Models\Concerns\OwnedByUser;
use Database\Factories\TemplateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Template extends Model
{
    /** @use HasFactory<TemplateFactory> */
    use HasFactory, OwnedByUser;

    protected $fillable = ['owner_id', 'name', 'description'];

    protected static function booted(): void
    {
        static::creating(function (Template $template) {
            $template->slug ??= self::uniqueSlug($template->name, $template->owner_id);
        });

        // Umbenennen ändert die Adresse; die alte leitet weiter.
        static::updating(function (Template $template) {
            if (! $template->isDirty('name')) {
                return;
            }
            $old = $template->getOriginal('slug');
            $template->slug = self::uniqueSlug($template->name, $template->owner_id, $template->id);
            if ($old && $old !== $template->slug) {
                DB::table('template_slugs')->insertOrIgnore(['template_id' => $template->id, 'owner_id' => $template->owner_id, 'slug' => $old]);
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * Adressen gelten je Person: aktuelle, frühere oder alte Nummer (Links von vor der Umstellung).
     * Fremde Vorlagen sind über die Adresse nicht auffindbar; per Nummer greift die Policy.
     */
    public function resolveRouteBinding($value, $field = null)
    {
        if ($field !== null && $field !== 'slug') {
            return parent::resolveRouteBinding($value, $field);
        }

        $ownerId = auth()->id();

        return static::where(['owner_id' => $ownerId, 'slug' => $value])->first()
            ?? static::whereIn('id', DB::table('template_slugs')->where(['owner_id' => $ownerId, 'slug' => $value])->select('template_id'))->first()
            ?? (ctype_digit((string) $value) ? static::find($value) : null);
    }

    /** „Städtetrip“ → staedtetrip, bei Gleichstand staedtetrip-2 …; reine Zahlen bekommen „vorlage-“ davor. */
    public static function uniqueSlug(string $name, int $ownerId, ?int $ignoreId = null): string
    {
        $base = trim(Str::limit(Str::slug($name, '-', 'de'), 60, ''), '-') ?: 'vorlage';
        if (ctype_digit($base)) {
            $base = 'vorlage-'.$base;
        }

        $taken = fn (string $slug) => static::where(['owner_id' => $ownerId, 'slug' => $slug])->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->exists()
            || DB::table('template_slugs')->where(['owner_id' => $ownerId, 'slug' => $slug])->where('template_id', '!=', $ignoreId ?? 0)->exists();

        if (! $taken($base)) {
            return $base;
        }
        for ($n = 2; $taken("{$base}-{$n}"); $n++);

        return "{$base}-{$n}";
    }

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
