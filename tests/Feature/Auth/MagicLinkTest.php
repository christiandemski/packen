<?php

use App\Mail\MagicLinkMail;
use App\Models\LoginToken;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

beforeEach(fn () => Mail::fake());

function requestLink(string $email): string
{
    test()->post(route('login.send'), ['email' => $email])->assertRedirect(route('login'));

    $mail = Mail::sent(MagicLinkMail::class)->last();

    return $mail->token;
}

it('verschickt einen Link an aktive Konten', function () {
    $user = User::factory()->create(['email' => 'anna@example.com']);

    $this->post(route('login.send'), ['email' => 'Anna@Example.com '])
        ->assertRedirect(route('login'))
        ->assertSessionHas('sent', 'anna@example.com');

    Mail::assertSent(MagicLinkMail::class, fn ($mail) => $mail->hasTo($user->email));
    expect(LoginToken::first()->token_hash)->toHaveLength(64);
});

it('antwortet für unbekannte Adressen genauso und verschickt nichts', function () {
    $this->post(route('login.send'), ['email' => 'niemand@example.com'])
        ->assertRedirect(route('login'))
        ->assertSessionHas('sent', 'niemand@example.com');

    Mail::assertNothingSent();
});

it('verschickt keinen Link an gesperrte Konten', function () {
    User::factory()->blocked()->create(['email' => 'gesperrt@example.com']);

    $this->post(route('login.send'), ['email' => 'gesperrt@example.com'])
        ->assertSessionHas('sent');

    Mail::assertNothingSent();
});

it('speichert den Token nur gehasht', function () {
    User::factory()->create(['email' => 'anna@example.com']);

    $token = requestLink('anna@example.com');

    expect(LoginToken::first()->token_hash)->toBe(hash('sha256', $token))->not->toBe($token);
});

it('meldet mit dem Link an und merkt sich die Sitzung', function () {
    $user = User::factory()->create(['email' => 'anna@example.com']);
    $token = requestLink('anna@example.com');

    $this->get(route('login.link', $token))->assertOk()->assertSee('Jetzt anmelden');

    $response = $this->post(route('login.consume', $token));

    $response->assertRedirect(route('home'));
    $this->assertAuthenticatedAs($user);
    expect($user->fresh()->last_login_at)->not->toBeNull();

    $remember = collect($response->headers->getCookies())->first(fn ($c) => str_starts_with($c->getName(), 'remember_web_'));
    expect($remember)->not->toBeNull()
        ->and($remember->getExpiresTime())->toBeGreaterThan(now()->addDays(29)->timestamp)
        ->and($remember->getExpiresTime())->toBeLessThan(now()->addDays(31)->timestamp);
});

it('lässt sich nur einmal verwenden', function () {
    User::factory()->create(['email' => 'anna@example.com']);
    $token = requestLink('anna@example.com');

    $this->post(route('login.consume', $token));
    $this->post(route('logout'));

    $this->post(route('login.consume', $token))->assertRedirect(route('login'))->assertSessionHasErrors('email');
    $this->assertGuest();
});

it('läuft nach 15 Minuten ab', function () {
    User::factory()->create(['email' => 'anna@example.com']);
    $token = requestLink('anna@example.com');

    $this->travel(16)->minutes();

    $this->get(route('login.link', $token))->assertSee('Link ungültig');
    $this->post(route('login.consume', $token))->assertSessionHasErrors('email');
    $this->assertGuest();
});

it('verbraucht den Link nicht beim bloßen Öffnen', function () {
    User::factory()->create(['email' => 'anna@example.com']);
    $token = requestLink('anna@example.com');

    $this->get(route('login.link', $token));
    $this->get(route('login.link', $token));

    expect(LoginToken::first()->used_at)->toBeNull();
});

it('macht ältere Links ungültig, wenn ein neuer angefordert wird', function () {
    User::factory()->create(['email' => 'anna@example.com']);
    $first = requestLink('anna@example.com');
    $second = requestLink('anna@example.com');

    $this->post(route('login.consume', $first))->assertSessionHasErrors('email');
    $this->post(route('login.consume', $second))->assertRedirect(route('home'));
});

it('begrenzt Anfragen pro E-Mail-Adresse', function () {
    User::factory()->create(['email' => 'anna@example.com']);

    foreach (range(1, 5) as $i) {
        $this->post(route('login.send'), ['email' => 'anna@example.com'])->assertSessionHasNoErrors();
    }

    $this->post(route('login.send'), ['email' => 'anna@example.com'])->assertSessionHasErrors('email');
    Mail::assertSentCount(5);
});

it('begrenzt Anfragen pro IP-Adresse', function () {
    foreach (range(1, 20) as $i) {
        $this->post(route('login.send'), ['email' => "test{$i}@example.com"])->assertSessionHasNoErrors();
    }

    $this->post(route('login.send'), ['email' => 'noch-einer@example.com'])->assertSessionHasErrors('email');
});

it('lehnt den Link ab, wenn das Konto inzwischen gesperrt ist', function () {
    $user = User::factory()->create(['email' => 'anna@example.com']);
    $token = requestLink('anna@example.com');

    $user->update(['active' => false]);

    $this->post(route('login.consume', $token))->assertSessionHasErrors('email');
    $this->assertGuest();
});
