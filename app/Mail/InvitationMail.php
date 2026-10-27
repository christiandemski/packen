<?php

namespace App\Mail;

use App\Models\Invitation;
use App\Services\InvitationService;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class InvitationMail extends Mailable
{
    public function __construct(public Invitation $invitation, public string $token) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Einladung zu '.config('app.name'));
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.invitation', with: [
            'url' => route('invitation.show', $this->token),
            'inviter' => $this->invitation->inviter?->name ?? config('app.name'),
            'days' => InvitationService::VALID_DAYS,
        ]);
    }
}
