<?php

use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('tut mit --from-env nichts, wenn kein Admin konfiguriert ist', function () {
    config(['app.bootstrap_admin' => ['email' => null, 'name' => 'Admin', 'password' => null]]);

    $this->artisan('packen:admin --from-env')->assertSuccessful();

    expect(User::count())->toBe(0);
});

it('legt mit --from-env den ersten Admin aus der .env an', function () {
    config(['app.bootstrap_admin' => ['email' => ' Chef@Example.org ', 'name' => 'Chef', 'password' => 'geheim-und-lang']]);

    $this->artisan('packen:admin --from-env')->assertSuccessful();

    $user = User::sole();
    expect($user->email)->toBe('chef@example.org')
        ->and($user->role)->toBe(Role::Admin)
        ->and(Hash::check('geheim-und-lang', $user->password))->toBeTrue();
});

it('überschreibt mit --from-env keinen bestehenden Benutzer', function () {
    $user = User::factory()->create(['email' => 'chef@example.org', 'password' => 'altes-passwort-123']);
    config(['app.bootstrap_admin' => ['email' => 'chef@example.org', 'name' => 'Chef', 'password' => 'neues-passwort-123']]);

    $this->artisan('packen:admin --from-env')->assertSuccessful();

    expect(Hash::check('altes-passwort-123', $user->fresh()->password))->toBeTrue();
});

it('lehnt mit --from-env ein zu kurzes Passwort ab', function () {
    config(['app.bootstrap_admin' => ['email' => 'chef@example.org', 'name' => 'Chef', 'password' => 'kurz']]);

    $this->artisan('packen:admin --from-env')->assertFailed();

    expect(User::count())->toBe(0);
});
