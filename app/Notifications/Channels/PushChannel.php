<?php

namespace App\Notifications\Channels;

use App\Models\User;
use App\Support\WebPush;
use Illuminate\Notifications\Notification;

/**
 * Schickt das Push-Signal an alle Geräte der Person. Den Text holt der Service Worker
 * danach selbst (Reminders::messagesFor zeigt frische Hinweise aus der Datenbank) –
 * deshalb muss der database-Kanal vorher laufen.
 */
class PushChannel
{
    public function send(User $notifiable, Notification $notification): void
    {
        foreach ($notifiable->pushSubscriptions()->get() as $device) {
            WebPush::send($device);
        }
    }
}
