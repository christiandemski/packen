<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Gemeinsamer Gegenstand einer Reise.
 * - planned: muss nur eine Person mitbringen (Adapter, Sonnencreme, Zelt)
 * - request: „Kann jemand … mitnehmen?“ – eine Person fragt, eine andere nimmt an
 */
class GroupItem extends Model
{
    public const PLANNED = 'planned';

    public const REQUEST = 'request';

    public const OPEN = 'open';

    public const CLAIMED = 'claimed';

    public const WITHDRAWN = 'withdrawn';

    protected $fillable = [
        'trip_id', 'name', 'note', 'kind', 'created_by', 'requested_by', 'assigned_user_id',
        'status', 'claimed_at', 'source_entry_id',
    ];

    protected function casts(): array
    {
        return ['claimed_at' => 'datetime'];
    }

    /** @return BelongsTo<Trip, $this> */
    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    /** Eintrag in der Packliste der Person, die es mitbringt. */
    public function entry(): HasOne
    {
        return $this->hasOne(PacklistEntry::class);
    }

    public function isRequest(): bool
    {
        return $this->kind === self::REQUEST;
    }

    public function isOpen(): bool
    {
        return $this->status === self::OPEN;
    }

    /** @param Builder<GroupItem> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', '!=', self::WITHDRAWN);
    }

    /** Name der fragenden Person – oder „Ehemaliges Mitglied“, wenn das Konto gelöscht ist. */
    public function requesterName(): string
    {
        return $this->requester?->name ?? 'Ehemaliges Mitglied';
    }
}
