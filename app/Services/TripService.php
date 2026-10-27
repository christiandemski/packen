<?php

namespace App\Services;

use App\Enums\TripMemberRole;
use App\Enums\TripMode;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Einziger Weg, Reisen anzulegen und zu ändern – später nutzt auch die API
 * für „unterwegs“ diesen Service.
 */
class TripService
{
    public const COVER_DISK = 'local';

    /**
     * @param  array{name: string, start_date: string, end_date: string, type: string, mode?: string,
     *     accommodation?: ?string, transport?: ?string, location?: ?string}  $data
     * @param  list<int>  $memberIds
     */
    public function create(User $owner, array $data, ?UploadedFile $cover = null, array $memberIds = []): Trip
    {
        return DB::transaction(function () use ($owner, $data, $cover, $memberIds) {
            $trip = Trip::create([
                ...$this->attributes($data),
                'owner_id' => $owner->id,
                'cover_path' => $cover ? $this->storeCover($cover) : null,
            ]);

            $trip->addMember($owner, TripMemberRole::Owner);
            $this->syncMembers($trip, $memberIds);

            return $trip;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<int>|null  $memberIds  null = Mitglieder unverändert lassen
     */
    public function update(Trip $trip, array $data, ?UploadedFile $cover = null, bool $removeCover = false, ?array $memberIds = null): Trip
    {
        return DB::transaction(function () use ($trip, $data, $cover, $removeCover, $memberIds) {
            $oldCover = $trip->cover_path;
            $attributes = $this->attributes($data);

            if ($cover !== null) {
                $attributes['cover_path'] = $this->storeCover($cover);
            } elseif ($removeCover) {
                $attributes['cover_path'] = null;
            }

            $trip->update($attributes);

            if ($oldCover !== null && $oldCover !== $trip->cover_path) {
                Storage::disk(self::COVER_DISK)->delete($oldCover);
            }

            if ($memberIds !== null || $trip->mode === TripMode::Solo) {
                $this->syncMembers($trip, $memberIds ?? []);
            }

            return $trip;
        });
    }

    public function delete(Trip $trip): void
    {
        if ($trip->cover_path !== null) {
            Storage::disk(self::COVER_DISK)->delete($trip->cover_path);
        }

        $trip->delete();
    }

    /** Mitglieder außer dem Owner abgleichen; private Reisen haben keine weiteren Mitglieder. */
    private function syncMembers(Trip $trip, array $memberIds): void
    {
        $wanted = $trip->mode === TripMode::Group
            ? User::whereIn('id', $memberIds)->where('active', true)->whereKeyNot($trip->owner_id)->pluck('id')
            : collect();

        $current = $trip->members()->whereKeyNot($trip->owner_id)->pluck('users.id');

        $trip->members()->detach($current->diff($wanted)->all());

        foreach (User::whereIn('id', $wanted->diff($current))->get() as $user) {
            $trip->addMember($user);
        }
    }

    private function attributes(array $data): array
    {
        return collect($data)->only([
            'name', 'start_date', 'end_date', 'type', 'mode', 'accommodation', 'transport', 'location',
        ])->all();
    }

    private function storeCover(UploadedFile $cover): string
    {
        return $cover->store('covers', self::COVER_DISK);
    }
}
