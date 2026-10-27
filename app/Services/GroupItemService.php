<?php

namespace App\Services;

use App\Enums\EntrySource;
use App\Enums\PacklistGroup;
use App\Models\GroupItem;
use App\Models\PacklistEntry;
use App\Models\Trip;
use App\Models\User;
use App\Notifications\TripNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Gruppen-Gegenstände und Anfragen in die Gruppe.
 *
 * Wichtigste Regel: Eine Anfrage kann nur eine Person annehmen. Das Annehmen ist ein
 * bedingtes Update auf status = open – gleichzeitige Versuche scheitern bis auf einen.
 */
class GroupItemService
{
    /** Geplanter Gegenstand, optional gleich jemandem zugewiesen. */
    public function plan(Trip $trip, User $creator, string $name, ?string $note = null, ?User $assignee = null): GroupItem
    {
        return DB::transaction(function () use ($trip, $creator, $name, $note, $assignee) {
            $item = GroupItem::create([
                'trip_id' => $trip->id,
                'name' => trim($name),
                'note' => $note,
                'kind' => GroupItem::PLANNED,
                'created_by' => $creator->id,
                'status' => GroupItem::OPEN,
            ]);

            if ($assignee !== null) {
                $this->claim($item, $assignee, notify: $assignee->isNot($creator));
            }

            return $item->fresh();
        });
    }

    /** „Kann jemand … mitnehmen?“ – optional aus einem Eintrag der eigenen Packliste. */
    public function request(Trip $trip, User $requester, string $name, ?string $note = null, ?PacklistEntry $sourceEntry = null): GroupItem
    {
        $item = GroupItem::create([
            'trip_id' => $trip->id,
            'name' => trim($name),
            'note' => $note,
            'kind' => GroupItem::REQUEST,
            'created_by' => $requester->id,
            'requested_by' => $requester->id,
            'status' => GroupItem::OPEN,
            'source_entry_id' => $sourceEntry?->user_id === $requester->id ? $sourceEntry->id : null,
        ]);

        $this->notify($trip, $trip->members()->whereKeyNot($requester->id)->get(),
            "{$requester->name} fragt: Kann jemand „{$item->name}“ mitnehmen?", 'request');

        return $item;
    }

    /**
     * Annehmen. Gibt false zurück, wenn jemand schneller war („Wurde schon übernommen“).
     */
    public function claim(GroupItem $item, User $user, bool $notify = true): bool
    {
        if ($item->isRequest() && $item->requested_by === $user->id) {
            return false; // die eigene Anfrage annehmen ergibt keinen Sinn
        }

        return DB::transaction(function () use ($item, $user, $notify) {
            $claimed = GroupItem::whereKey($item->id)
                ->where('status', GroupItem::OPEN)
                ->update(['status' => GroupItem::CLAIMED, 'assigned_user_id' => $user->id, 'claimed_at' => now()]);

            if ($claimed !== 1) {
                return false;
            }

            $item->refresh();

            PacklistEntry::create([
                'trip_id' => $item->trip_id,
                'user_id' => $user->id,
                'group_item_id' => $item->id,
                'custom_name' => $item->name,
                'group' => PacklistGroup::Shared->value,
                'quantity' => 1,
                'source' => EntrySource::GroupItem,
                'note' => $item->isRequest() ? 'für '.$item->requesterName() : $item->note,
            ]);

            // Bei der fragenden Person gilt der Gegenstand jetzt als erledigt.
            $item->source_entry_id && PacklistEntry::whereKey($item->source_entry_id)->update(['excluded' => true]);

            if ($notify && $item->isRequest() && $item->requester) {
                $this->notify($item->trip, collect([$item->requester]), "{$user->name} bringt „{$item->name}“ mit.", 'claimed');
            }

            return true;
        });
    }

    /** Wieder abgeben: Die Anfrage ist wieder offen, der Eintrag verschwindet. */
    public function release(GroupItem $item, User $user): bool
    {
        if ($item->assigned_user_id !== $user->id || $item->status !== GroupItem::CLAIMED) {
            return false;
        }

        DB::transaction(function () use ($item) {
            $item->entry()->delete();
            $item->update(['status' => GroupItem::OPEN, 'assigned_user_id' => null, 'claimed_at' => null]);
            $item->source_entry_id && PacklistEntry::whereKey($item->source_entry_id)->update(['excluded' => false]);
        });

        if ($item->isRequest() && $item->requester && $item->requester->isNot($user)) {
            $this->notify($item->trip, collect([$item->requester]), "{$user->name} kann „{$item->name}“ doch nicht mitbringen – die Anfrage ist wieder offen.", 'released');
        }

        return true;
    }

    /** Zurückziehen (Anfrage) bzw. streichen (geplant). Wer es schon mitbringen wollte, wird informiert. */
    public function withdraw(GroupItem $item, User $user): bool
    {
        $allowed = $item->isRequest() ? $item->requested_by === $user->id : ($item->created_by === $user->id || $item->trip->isOwnedBy($user));
        if (! $allowed || $item->status === GroupItem::WITHDRAWN) {
            return false;
        }

        $assignee = $item->assignee;

        DB::transaction(function () use ($item) {
            $item->entry()->delete();
            $item->update(['status' => GroupItem::WITHDRAWN]);
            $item->source_entry_id && PacklistEntry::whereKey($item->source_entry_id)->update(['excluded' => false]);
        });

        if ($assignee && $assignee->isNot($user)) {
            $this->notify($item->trip, collect([$assignee]), "„{$item->name}“ wird nicht mehr gebraucht – der Eintrag ist aus deiner Liste entfernt.", 'withdrawn');
        }

        return true;
    }

    /** Wer eine Reise verlässt (oder sein Konto löscht), gibt Übernommenes wieder frei. */
    public function releaseAllFor(User $user, ?Trip $trip = null): void
    {
        GroupItem::where('assigned_user_id', $user->id)
            ->where('status', GroupItem::CLAIMED)
            ->when($trip, fn ($q) => $q->where('trip_id', $trip->id))
            ->update(['status' => GroupItem::OPEN, 'assigned_user_id' => null, 'claimed_at' => null]);
    }

    private function notify(Trip $trip, $users, string $message, string $kind): void
    {
        $recipients = $users->filter(fn (User $u) => $u->active);
        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new TripNotification($trip, $message, $kind));
        }
    }
}
