<?php

use App\Enums\AuthSource;
use App\Enums\Role;
use App\Models\User;
use App\Services\MagicLinkService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Validator;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

Artisan::command('packen:admin {email?} {name?}', function (?string $email = null, ?string $name = null) {
    $email = MagicLinkService::normalize($email ?? text('E-Mail-Adresse', required: true));
    $name ??= text('Name', required: true);
    $password = password('Passwort (mindestens 12 Zeichen)', required: true, validate: fn ($value) => mb_strlen($value) < 12 ? 'Mindestens 12 Zeichen.' : null);

    if (Validator::make(['email' => $email], ['email' => 'email'])->fails()) {
        $this->error('Ungültige E-Mail-Adresse.');

        return 1;
    }

    $user = User::updateOrCreate(['email' => $email], [
        'name' => $name,
        'role' => Role::Admin,
        'auth_source' => AuthSource::Local,
        'active' => true,
        'password' => $password,
    ]);

    $this->info("Admin {$user->email} ist eingerichtet.");
})->purpose('Lokalen Admin anlegen oder dessen Passwort setzen');
