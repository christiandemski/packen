<?php

namespace App\Support;

use App\Enums\ItemCategory;
use App\Enums\PacklistGroup;
use App\Models\Category;

/**
 * Eine Überschrift samt Icon, unter der Gegenstände stehen – feste Kategorie bzw.
 * Packlisten-Karte oder eine eigene Kategorie. Eigene stehen direkt vor „Sonstiges“.
 */
final class Group
{
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly string $icon,
        private readonly float $position,
    ) {}

    /** Feste Kategorie im Katalog (Kleidung aufgeteilt in Oberteile, Hosen, …). */
    public static function category(ItemCategory $category): self
    {
        return new self($category->value, $category->label(), $category->icon(), $category->position());
    }

    /** Karte der Packliste (alle Kleidung zusammen). */
    public static function packlist(PacklistGroup $group): self
    {
        return new self($group->value, $group->label(), $group->icon(), $group->position());
    }

    public static function custom(Category $category, bool $packlist = false): self
    {
        $misc = $packlist ? PacklistGroup::Misc->position() : ItemCategory::Misc->position();

        return new self($category->key(), $category->name, $category->icon, $misc - 0.5);
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
        return sprintf('%07.2f|%s', $this->position, mb_strtolower($this->label));
    }
}
