<?php

namespace App\Support;

use App\Enums\ItemCategory;
use App\Enums\PacklistGroup;
use App\Models\Category;

/**
 * Eine Überschrift samt Icon, unter der Gegenstände stehen – feste Kategorie bzw.
 * Packlisten-Karte oder eine eigene Kategorie. Eigene stehen ohne eigene Reihenfolge
 * direkt vor „Sonstiges“; mit eigener Reihenfolge (CategorySettings::order) dort, wo man sie hinzieht.
 */
final class Group
{
    /** Packlisten-Karten, die immer oben stehen */
    private const PINNED = [PacklistGroup::DepartureDay, PacklistGroup::Tasks, PacklistGroup::Shared];

    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly string $icon,
        private readonly float $position,
    ) {}

    /** Feste Kategorie im Katalog (Kleidung aufgeteilt in Oberteile, Hosen, …). */
    public static function category(ItemCategory $category): self
    {
        return new self($category->value, $category->label(), $category->icon(), self::position($category->value));
    }

    /** Karte der Packliste (alle Kleidung zusammen). */
    public static function packlist(PacklistGroup $group): self
    {
        $pinned = array_search($group, self::PINNED, true);

        $position = match (true) {
            $pinned !== false => $pinned - count(self::PINNED),
            // „Kleidung“ steht dort, wo die erste Kleidungskategorie steht
            $group === PacklistGroup::Clothing => min(array_map(fn (ItemCategory $c) => self::position($c->value), ItemCategory::clothing())),
            default => self::position($group->value),
        };

        return new self($group->value, $group->label(), $group->icon(), $position);
    }

    public static function custom(Category $category, bool $packlist = false): self
    {
        return new self($category->key(), $category->name, $category->icon, self::position($category->key()));
    }

    /** Für Freitext-Einträge, die ihre Karte als Schlüssel gespeichert haben. */
    public static function fromPacklistKey(?string $key): self
    {
        $id = Category::idFromKey($key);
        $category = $id === null ? null : Category::find($id);

        return $category
            ? self::custom($category, packlist: true)
            : self::packlist(PacklistGroup::tryFrom((string) $key) ?? PacklistGroup::Misc);
    }

    public function is(PacklistGroup|ItemCategory $builtin): bool
    {
        return $this->key === $builtin->value;
    }

    public function isCustom(): bool
    {
        return Category::idFromKey($this->key) !== null;
    }

    /** Zum Sortieren mehrerer Gruppen: erst Position, dann Name. */
    public function sortKey(): string
    {
        return sprintf('%08.2f|%s', $this->position + 100, mb_strtolower($this->label));
    }

    /**
     * Position einer Kategorie: aus der eigenen Reihenfolge, sonst Standard
     * (feste in Enum-Reihenfolge, eigene direkt vor „Sonstiges“). Neue Kategorien,
     * die in der eigenen Reihenfolge noch fehlen, landen dahinter.
     */
    private static function position(string $key): float
    {
        $default = ItemCategory::tryFrom($key)?->position() ?? ItemCategory::Misc->position() - 0.5;
        $order = CategorySettings::order();

        if ($order === []) {
            return $default;
        }

        return $order[$key] ?? count($order) + $default;
    }
}
