<?php

namespace App\Models;

use App\Enums\EntrySource;
use App\Enums\PacklistGroup;
use App\Support\Group;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class PacklistEntry extends Model
{
    protected $fillable = [
        'trip_id', 'user_id', 'item_id', 'group_item_id', 'packed_by', 'custom_name', 'group', 'container_id', 'quantity', 'quantity_locked',
        'source', 'excluded', 'note', 'packed', 'packed_at', 'packed_changed_at', 'returned', 'returned_changed_at', 'review',
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
            'returned' => 'boolean',
            'returned_changed_at' => 'datetime',
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

    /** @return BelongsTo<Container, $this> */
    public function groupItem(): BelongsTo
    {
        return $this->belongsTo(GroupItem::class);
    }

    /** Wer (wenn nicht die Person selbst) den Eintrag abgehakt hat. */
    public function packer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'packed_by');
    }

    /** @return BelongsTo<Container, $this> */
    public function container(): BelongsTo
    {
        return $this->belongsTo(Container::class);
    }

    /** Gewicht des Eintrags in Gramm (0, wenn am Gegenstand nichts gepflegt ist). */
    public function weight(): int
    {
        return ($this->item?->weight_g ?? 0) * $this->quantity;
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

    /** „Vor der Abfahrt erledigen“: wird abgehakt, aber nicht eingepackt (kein Gewicht, kein Behältnis). */
    public function isTask(): bool
    {
        return $this->item !== null
            ? $this->item->category_id === null && $this->item->category->isTask()
            : $this->group_item_id === null && $this->group === PacklistGroup::Tasks->value;
    }

    public function packlistGroup(): Group
    {
        if ($this->group_item_id !== null) {
            return Group::packlist(PacklistGroup::Shared);
        }

        return $this->item?->packlistGroup() ?? Group::fromPacklistKey($this->group);
    }

    /**
     * Abhaken mit „letzter Stand gewinnt“: Eine Änderung, die laut Gerät älter ist als die
     * gespeicherte, wird verworfen (z. B. ein spät synchronisierter Offline-Haken).
     *
     * @return bool ob die Änderung übernommen wurde
     */
    public function setPacked(bool $packed, ?Carbon $changedAt = null, ?User $by = null): bool
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
            // Nur merken, wenn jemand anderes gepackt hat („gepackt von Max“)
            'packed_by' => $packed && $by !== null && $by->id !== $this->user_id ? $by->id : null,
        ]);

        return true;
    }

    /** Rückreise-Check: „wieder eingepackt“, ebenfalls mit „letzter Stand gewinnt“ (siehe setPacked). */
    public function setReturned(bool $returned, ?Carbon $changedAt = null): bool
    {
        $changedAt = $changedAt === null ? now() : $changedAt->copy()->min(now());

        if ($this->returned_changed_at !== null && $this->returned_changed_at->gt($changedAt)) {
            return false;
        }

        $this->update(['returned' => $returned, 'returned_changed_at' => $changedAt]);

        return true;
    }
}
