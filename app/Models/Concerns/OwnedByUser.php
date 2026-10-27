<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Einzige Stelle, die festlegt, wem Katalog-Daten gehören. Kommt später der Haushalt
 * dazu, werden nur scopeAccessibleBy() und isAccessibleBy() erweitert.
 */
trait OwnedByUser
{
    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function scopeAccessibleBy(Builder $query, User $user): void
    {
        $query->where($this->qualifyColumn('owner_id'), $user->id);
    }

    public function isAccessibleBy(User $user): bool
    {
        return $this->owner_id === $user->id;
    }
}
