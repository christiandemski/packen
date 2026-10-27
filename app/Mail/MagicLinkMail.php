<?php

namespace App\Mail;

use App\Models\User;
use App\Services\MagicLinkService;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class MagicLinkMail extends Mailable
{
    public function __construct(public User $user, public string $token) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Dein Anmeldelink für '.config('app.name'));
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.magic-link', with: [
            'url' => route('login.link', $this->token),
            'minutes' => MagicLinkService::VALID_MINUTES,
        ]);
    }
}
