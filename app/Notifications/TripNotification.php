<?php

namespace App\Notifications;

use App\Enums\PushKind;
use App\Models\Trip;
use App\Notifications\Channels\PushChannel;
use App\Support\WebPush;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Hinweis zu einer Reise („Anna fragt: Hat jemand einen Föhn?“). Immer in der App,
 * per E-Mail bzw. Push nur, wenn die Person das unter „Benachrichtigungen“ eingeschaltet hat.
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
        // database zuerst: Der Service Worker holt den Text nach dem Push aus der Datenbank.
        return array_values(array_filter([
            'database',
            $notifiable->email_notifications ? 'mail' : null,
            WebPush::enabled() && $notifiable->wantsPush(PushKind::Group) && $notifiable->pushSubscriptions()->exists() ? PushChannel::class : null,
        ]));
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
