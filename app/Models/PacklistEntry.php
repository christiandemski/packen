<?php

namespace App\Models;

use App\Enums\EntrySource;
use App\Enums\PacklistGroup;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class PacklistEntry extends Model
{
    protected $fillable = [
        'trip_id', 'user_id', 'item_id', 'custom_name', 'group', 'quantity', 'quantity_locked',
        'source', 'excluded', 'note', 'packed', 'packed_at', 'packed_changed_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'quantity_locked' => 'boolean',
            'source' => EntrySource::class,
            'excluded' => 'boolean',
            'packed' => 'boolean',
            'packed_at' => 'datetime',
            'packed_changed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Trip, $this> */
    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Item, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    /** Sichtbare Einträge (bewusst entfernte Vorlagen-Einträge bleiben nur als Merker liegen). */
    public function scopeVisible(Builder $query): void
    {
        $query->where('excluded', false);
    }

    public function name(): string
    {
        return $this->item?->name ?? $this->custom_name ?? 'Unbenannt';
    }

    public function packlistGroup(): PacklistGroup
    {
        if ($this->item === null) {
            return PacklistGroup::tryFrom((string) $this->group) ?? PacklistGroup::Misc;
        }

        return $this->item->pack_last ? PacklistGroup::DepartureDay : PacklistGroup::forCategory($this->item->category);
    }

    /**
     * Abhaken mit „letzter Stand gewinnt“: Eine Änderung, die laut Gerät älter ist als die
     * gespeicherte, wird verworfen (z. B. ein spät synchronisierter Offline-Haken).
     *
     * @return bool ob die Änderung übernommen wurde
     */
    public function setPacked(bool $packed, ?Carbon $changedAt = null): bool
    {
        // Gerätezeit nie in der Zukunft akzeptieren, sonst blockiert eine falsch gehende Uhr spätere Änderungen.
        $changedAt = $changedAt === null ? now() : $changedAt->copy()->min(now());

        if ($this->packed_changed_at !== null && $this->packed_changed_at->gt($changedAt)) {
            return false;
        }

        $this->update([
            'packed' => $packed,
            'packed_at' => $packed ? ($this->packed ? $this->packed_at : $changedAt) : null,
            'packed_changed_at' => $changedAt,
        ]);

        return true;
    }
}
