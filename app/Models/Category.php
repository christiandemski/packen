<?php

namespace App\Models;

use App\Enums\ItemCategory;
use App\Enums\PacklistGroup;
use App\Models\Concerns\OwnedByUser;
use App\Support\Group;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/** Eigene Kategorie im Katalog, z. B. „Kamera“ oder „Hund“. Gilt nie als Kleidung. */
class Category extends Model
{
    use OwnedByUser;

    /** Schlüssel-Präfix, mit dem eigene Kategorien neben den festen auftauchen („c:12“). */
    public const PREFIX = 'c:';

    /** Zur Auswahl stehende Icons (siehe x-icon). */
    public const ICONS = [
        'package', 'backpack', 'luggage', 'briefcase', 'shopping-bag', 'gift',
        'shirt', 'layers', 'footprints', 'glasses', 'dumbbell', 'trophy',
        'droplet', 'pill', 'heart', 'scissors', 'bed', 'sun',
        'plug', 'camera', 'gamepad', 'music', 'book-open', 'printer',
        'wallet', 'key', 'wrench', 'compass', 'map-pin', 'tent',
        'mountain', 'bike', 'car', 'plane', 'ship', 'waves',
        'umbrella', 'snowflake', 'flame', 'utensils', 'coffee', 'star',
    ];

    protected $fillable = ['owner_id', 'name', 'icon'];

    /** @return HasMany<Item, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(Item::class);
    }

    public function key(): string
    {
        return self::PREFIX.$this->id;
    }

    public static function idFromKey(?string $key): ?int
    {
        return $key !== null && str_starts_with($key, self::PREFIX) ? ((int) substr($key, strlen(self::PREFIX)) ?: null) : null;
    }

    /**
     * Alle Kategorien zum Auswählen: feste und eigene, in Katalog-Reihenfolge.
     *
     * @return Collection<int, Group>
     */
    public static function choices(User $user): Collection
    {
        return collect(ItemCategory::cases())->map(fn (ItemCategory $c) => Group::category($c))
            ->merge(self::accessibleBy($user)->orderBy('name')->get()->map(fn (self $c) => Group::custom($c)))
            ->sortBy(fn (Group $g) => $g->sortKey())
            ->values();
    }

    /**
     * Karten der Packliste für Freitext-Einträge: feste und eigene.
     *
     * @return Collection<int, Group>
     */
    public static function packlistChoices(User $user): Collection
    {
        return collect(PacklistGroup::cases())->map(fn (PacklistGroup $g) => Group::packlist($g))
            ->merge(self::accessibleBy($user)->orderBy('name')->get()->map(fn (self $c) => Group::custom($c, packlist: true)))
            ->sortBy(fn (Group $g) => $g->sortKey())
            ->values();
    }

    /**
     * Übersetzt einen Auswahl-Schlüssel („tops“ oder „c:12“) in die Spalten des Gegenstands.
     * Unbekannte oder fremde Kategorien landen unter „Sonstiges“.
     *
     * @return array{category: string, category_id: ?int}
     */
    public static function columnsFor(?string $key, User $user): array
    {
        $id = self::idFromKey($key);

        if ($id !== null && self::accessibleBy($user)->whereKey($id)->exists()) {
            return ['category' => ItemCategory::Misc->value, 'category_id' => $id];
        }

        return ['category' => (ItemCategory::tryFrom((string) $key) ?? ItemCategory::Misc)->value, 'category_id' => null];
    }
}
