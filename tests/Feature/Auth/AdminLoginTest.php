<?php

use App\Models\User;

it('meldet den Admin mit Passwort an', function () {
    $admin = User::factory()->admin()->create(['email' => 'admin@example.com']);

    $this->post(route('admin.login.store'), ['email' => 'admin@example.com', 'password' => 'geheim-passwort'])
        ->assertRedirect(route('home'));

    $this->assertAuthenticatedAs($admin);
});

it('lehnt falsche Passwörter ab', function () {
    User::factory()->admin()->create(['email' => 'admin@example.com']);

    $this->post(route('admin.login.store'), ['email' => 'admin@example.com', 'password' => 'falsch'])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('lässt Freunde nicht über den Admin-Login herein', function () {
    User::factory()->create(['email' => 'anna@example.com', 'password' => 'geheim-passwort']);

    $this->post(route('admin.login.store'), ['email' => 'anna@example.com', 'password' => 'geheim-passwort'])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('begrenzt Fehlversuche', function () {
    User::factory()->admin()->create(['email' => 'admin@example.com']);

    foreach (range(1, 5) as $i) {
        $this->post(route('admin.login.store'), ['email' => 'admin@example.com', 'password' => 'falsch']);
    }

    $this->post(route('admin.login.store'), ['email' => 'admin@example.com', 'password' => 'geheim-passwort'])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('leitet Gäste zur Anmeldung um', function () {
    $this->get(route('home'))->assertRedirect(route('login'));
});

it('meldet ab', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('logout'))
        ->assertRedirect(route('login'));

    $this->assertGuest();
});
