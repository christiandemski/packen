<?php

namespace App\Models;

use App\Enums\Accommodation;
use App\Enums\Transport;
use App\Enums\TripMemberRole;
use App\Enums\TripMode;
use App\Enums\TripType;
use Database\Factories\TripFactory;
use Illuminate\Database\Eloquent\Casts\AsEnumCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Trip extends Model
{
    /** @use HasFactory<TripFactory> */
    use HasFactory;

    protected $fillable = [
        'owner_id', 'external_id', 'external_url', 'name', 'start_date', 'end_date', 'type', 'mode',
        'accommodation', 'transports', 'location', 'cover_path',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'type' => TripType::class,
            'mode' => TripMode::class,
            'accommodation' => Accommodation::class,
            'transports' => AsEnumCollection::of(Transport::class),
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /** @return BelongsToMany<User, $this> */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'trip_members')
            ->using(TripMember::class)
            ->withPivot(['role', 'outfits_shared', 'joined_at']);
    }

    /** @return HasMany<TripStage, $this> */
    public function stages(): HasMany
    {
        return $this->hasMany(TripStage::class)->orderBy('date');
    }

    /**
     * Ort je Tag: Etappe, sonst Reiseziel.
     *
     * @return array<string, ?string> Datum (Y-m-d) => Ort
     */
    public function locationsByDate(): array
    {
        $stages = $this->stages->mapWithKeys(fn (TripStage $stage) => [$stage->date->toDateString() => $stage->location]);

        $locations = [];
        for ($date = $this->start_date->copy(); $date->lte($this->end_date); $date->addDay()) {
            $locations[$date->toDateString()] = $stages[$date->toDateString()] ?? $this->location;
        }

        return $locations;
    }

    /** @return HasMany<PacklistEntry, $this> */
    public function entries(): HasMany
    {
        return $this->hasMany(PacklistEntry::class);
    }

    /** Für die Packliste gewählte Vorlagen einer Person. */
    public function templatesFor(User $user): BelongsToMany
    {
        return $this->belongsToMany(Template::class, 'trip_templates')->wherePivot('user_id', $user->id);
    }

    /** Behältnisse, die eine Person auf diese Reise mitnimmt. */
    public function containersFor(User $user): BelongsToMany
    {
        return $this->belongsToMany(Container::class, 'trip_containers')->wherePivot('user_id', $user->id);
    }

    public function hasMember(User $user): bool
    {
        return $this->members()->whereKey($user->id)->exists();
    }

    public function isOwnedBy(User $user): bool
    {
        return $this->owner_id === $user->id;
    }

    public function isGroup(): bool
    {
        return $this->mode === TripMode::Group;
    }

    public function days(): int
    {
        return (int) $this->start_date->diffInDays($this->end_date) + 1;
    }

    public function nights(): int
    {
        return $this->days() - 1;
    }

    /** z. B. „5 Tage · 4 Nächte“; bei Tagesausflügen nur „1 Tag“. */
    public function durationLabel(): string
    {
        $days = $this->days();
        $nights = $this->nights();
        $label = $days.' '.($days === 1 ? 'Tag' : 'Tage');

        if ($nights > 0) {
            $label .= ' · '.$nights.' '.($nights === 1 ? 'Nacht' : 'Nächte');
        }

        return $label;
    }

    /** @return 'upcoming'|'running'|'past' */
    public function status(?Carbon $today = null): string
    {
        $today ??= today();

        return match (true) {
            $today->lt($this->start_date) => 'upcoming',
            $today->gt($this->end_date) => 'past',
            default => 'running',
        };
    }

    /** Kurzer Hinweis wie „in 12 Tagen“, „Tag 2 von 5“ oder „Vorbei“. */
    public function countdownLabel(?Carbon $today = null): string
    {
        $today ??= today();

        return match ($this->status($today)) {
            'upcoming' => ($days = (int) $today->diffInDays($this->start_date)) === 1 ? 'Morgen' : "in {$days} Tagen",
            'running' => 'Tag '.((int) $this->start_date->diffInDays($today) + 1).' von '.$this->days(),
            default => 'Vorbei',
        };
    }

    /** z. B. „12.–16. Mai“, „28. Mai – 3. Juni“, „28. Dez 2026 – 3. Jan 2027“; Jahr nur außerhalb des laufenden Jahres. */
    public function dateRangeLabel(): string
    {
        $start = $this->start_date;
        $end = $this->end_date;
        $suffix = $end->year !== today()->year ? ' '.$end->year : '';

        if ($start->isSameDay($end)) {
            return $start->translatedFormat('j. F').$suffix;
        }

        if ($start->isSameMonth($end)) {
            return $start->format('j.').'–'.$end->translatedFormat('j. F').$suffix;
        }

        if ($start->year === $end->year) {
            return $start->translatedFormat('j. F').' – '.$end->translatedFormat('j. F').$suffix;
        }

        return $start->translatedFormat('j. M Y').' – '.$end->translatedFormat('j. M Y');
    }

    public function addMember(User $user, TripMemberRole $role = TripMemberRole::Member): void
    {
        $this->members()->syncWithoutDetaching([
            $user->id => ['role' => $role->value, 'joined_at' => now()],
        ]);
    }
}
