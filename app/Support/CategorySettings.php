<?php

namespace App\Support;

use App\Enums\ItemCategory;
use App\Models\User;
use ArrayObject;
use Illuminate\Support\Facades\DB;

/**
 * Eigene Namen und ausgeblendete feste Kategorien – gelten für die angemeldete Person
 * (sie sieht ihre Namen auch auf Listen, die andere für sie freigegeben haben).
 */
final class CategorySettings
{
    public static function label(ItemCategory $category, ?User $user = null): ?string
    {
        return self::for($user)[$category->value]->label ?? null;
    }

    /** @return list<string> Werte der ausgeblendeten Kategorien */
    public static function hidden(?User $user = null): array
    {
        return array_keys(array_filter(self::for($user), fn ($row) => (bool) $row->hidden));
    }

    public static function save(User $user, ItemCategory $category, ?string $label, bool $hidden): void
    {
        $label = filled($label) && $label !== $category->defaultLabel() ? $label : null;
        $hidden = $hidden && $category->canBeHidden();

        if ($label === null && ! $hidden) {
            DB::table('category_settings')->where(['owner_id' => $user->id, 'category' => $category->value])->delete();
        } else {
            DB::table('category_settings')->updateOrInsert(
                ['owner_id' => $user->id, 'category' => $category->value],
                ['label' => $label, 'hidden' => $hidden, 'updated_at' => now(), 'created_at' => now()],
            );
        }

        unset(self::cache()[$user->id]);
    }

    /** @return array<string, object> */
    private static function for(?User $user): array
    {
        $id = $user?->id ?? auth()->id();
        if ($id === null) {
            return [];
        }

        $cache = self::cache();

        return $cache[$id] ??= DB::table('category_settings')->where('owner_id', $id)->get(['category', 'label', 'hidden'])->keyBy('category')->all();
    }

    /** Liegt im Container – je Anfrage bzw. Test neu, damit nichts Veraltetes hängen bleibt. */
    private static function cache(): ArrayObject
    {
        if (! app()->bound(self::class)) {
            app()->instance(self::class, new ArrayObject);
        }

        return app(self::class);
    }
}
