<?php

use App\Enums\AuthSource;
use App\Enums\Role;
use App\Models\User;
use App\Services\MagicLinkService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Validator;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

Artisan::command('packen:admin {email?} {name?} {--from-env : Ohne Rückfragen aus PACKEN_ADMIN_* der .env anlegen (für deploy.sh)}', function (?string $email = null, ?string $name = null) {
    if ($this->option('from-env')) {
        $config = config('app.bootstrap_admin');
        if (blank($config['email']) || blank($config['password'])) {
            return 0; // nichts konfiguriert – der Normalfall nach der Einrichtung
        }

        $email = MagicLinkService::normalize($config['email']);
        if (User::where('email', $email)->exists()) {
            $this->warn("Admin {$email} existiert bereits – PACKEN_ADMIN_PASSWORD kann aus der .env gelöscht werden.");

            return 0;
        }
        if (mb_strlen($config['password']) < 12 || Validator::make(['email' => $email], ['email' => 'email'])->fails()) {
            $this->error('PACKEN_ADMIN_EMAIL ungültig oder PACKEN_ADMIN_PASSWORD kürzer als 12 Zeichen.');

            return 1;
        }

        User::create([
            'email' => $email,
            'name' => $config['name'] ?: 'Admin',
            'role' => Role::Admin,
            'auth_source' => AuthSource::Local,
            'active' => true,
            'password' => $config['password'],
        ]);
        $this->info("Admin {$email} angelegt. Jetzt PACKEN_ADMIN_PASSWORD aus der .env löschen.");

        return 0;
    }

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
