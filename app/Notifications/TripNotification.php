<?php

namespace App\Notifications;

use App\Models\Trip;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Hinweis zu einer Reise („Anna fragt: Hat jemand einen Föhn?“). Immer in der App,
 * per E-Mail nur, wenn die Person das im Profil eingeschaltet hat.
 */
class TripNotification extends Notification
{
    public function __construct(
        public Trip $trip,
        public string $message,
        public string $kind,
    ) {}

    public function via(object $notifiable): array
    {
        return $notifiable->email_notifications ? ['database', 'mail'] : ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'trip_id' => $this->trip->id,
            'trip_name' => $this->trip->name,
            'message' => $this->message,
            'kind' => $this->kind,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->trip->name.': '.$this->message)
            ->greeting('Hallo '.$notifiable->name.',')
            ->line($this->message)
            ->action('Zur Reise', route('trips.show', $this->trip))
            ->salutation('Viele Grüße, '.config('app.name'));
    }
}
