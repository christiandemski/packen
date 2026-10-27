<?php

use App\Enums\Role;
use App\Mail\InvitationMail;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();
    $this->admin = User::factory()->admin()->create();
});

function invite(string $email = 'neu@example.com', string $name = 'Neu'): string
{
    test()->actingAs(test()->admin)
        ->post(route('admin.invitations.store'), ['name' => $name, 'email' => $email])
        ->assertRedirect(route('admin.index'));

    test()->post(route('logout'));

    return Mail::sent(InvitationMail::class)->last()->token;
}

it('lässt den Admin Freunde einladen', function () {
    invite('Neu@Example.com');

    Mail::assertSent(InvitationMail::class, fn ($mail) => $mail->hasTo('neu@example.com'));
    expect(Invitation::first())
        ->email->toBe('neu@example.com')
        ->invited_by->toBe($this->admin->id);
});

it('verbietet Freunden das Einladen und die Admin-Seite', function () {
    $friend = User::factory()->create();

    $this->actingAs($friend)->get(route('admin.index'))->assertForbidden();
    $this->actingAs($friend)->post(route('admin.invitations.store'), ['name' => 'X', 'email' => 'x@example.com'])->assertForbidden();

    Mail::assertNothingSent();
});

it('lädt keine Adresse ein, die schon ein Konto hat', function () {
    User::factory()->create(['email' => 'anna@example.com']);

    $this->actingAs($this->admin)
        ->post(route('admin.invitations.store'), ['name' => 'Anna', 'email' => 'anna@example.com'])
        ->assertSessionHasErrorsIn('invite', 'email');
});

it('legt beim Annehmen ein Freundeskonto an und meldet an', function () {
    $token = invite('neu@example.com', 'Neu');

    $this->get(route('invitation.show', $token))->assertOk()->assertSee('Konto aktivieren');
    $this->post(route('invitation.accept', $token), ['name' => 'Neue Person'])->assertRedirect(route('home'));

    $user = User::where('email', 'neu@example.com')->first();
    expect($user)->name->toBe('Neue Person')->role->toBe(Role::Friend)->password->toBeNull();
    $this->assertAuthenticatedAs($user);
    expect(Invitation::first()->accepted_at)->not->toBeNull();
});

it('lässt sich nur einmal annehmen', function () {
    $token = invite();
    $this->post(route('invitation.accept', $token), ['name' => 'Neu']);
    $this->post(route('logout'));

    $this->post(route('invitation.accept', $token), ['name' => 'Nochmal'])->assertRedirect(route('invitation.show', $token));
    expect(User::where('email', 'neu@example.com')->count())->toBe(1);
});

it('läuft nach 7 Tagen ab', function () {
    $token = invite();

    $this->travel(8)->days();

    $this->get(route('invitation.show', $token))->assertSee('Einladung ungültig');
    $this->post(route('invitation.accept', $token), ['name' => 'Neu']);
    $this->assertGuest();
});

it('macht beim erneuten Senden den alten Link ungültig', function () {
    $old = invite();
    $invitation = Invitation::first();

    $this->actingAs($this->admin)->post(route('admin.invitations.resend', $invitation));
    $this->post(route('logout'));
    $new = Mail::sent(InvitationMail::class)->last()->token;

    $this->post(route('invitation.accept', $old), ['name' => 'Neu']);
    $this->assertGuest();
    $this->post(route('invitation.accept', $new), ['name' => 'Neu'])->assertRedirect(route('home'));
});

it('lässt den Admin Einladungen zurückziehen', function () {
    $token = invite();

    $this->actingAs($this->admin)->delete(route('admin.invitations.destroy', Invitation::first()));
    $this->post(route('logout'));

    $this->get(route('invitation.show', $token))->assertSee('Einladung ungültig');
});
