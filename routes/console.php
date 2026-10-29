<?php

use App\Enums\AuthSource;
use App\Enums\Role;
use App\Models\User;
use App\Services\MagicLinkService;
use App\Services\Reminders;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

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

Artisan::command('packen:push-einrichten', function () {
    $path = app()->environmentFilePath();
    $env = is_file($path) ? file_get_contents($path) : '';
    $missing = [];

    if (! preg_match('/^VAPID_PRIVATE_KEY=.+$/m', $env)) {
        $key = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        if ($key === false) {
            $this->error('OpenSSL kann keinen Schlüssel erzeugen (fehlt die openssl.cnf?).');

            return 1;
        }
        openssl_pkey_export($key, $pem);
        $missing['VAPID_PRIVATE_KEY'] = base64_encode($pem);
    }
    if (! preg_match('/^CRON_TOKEN=.+$/m', $env)) {
        $missing['CRON_TOKEN'] = Str::random(40);
    }

    if ($missing === []) {
        $this->line('Push ist eingerichtet.');

        return 0;
    }

    // Leere Zeilen „KEY=“ ersetzen, sonst anhängen
    foreach ($missing as $name => $value) {
        $env = preg_match("/^{$name}=\$/m", $env)
            ? preg_replace("/^{$name}=\$/m", "{$name}={$value}", $env)
            : rtrim($env).PHP_EOL."{$name}={$value}".PHP_EOL;
    }
    file_put_contents($path, $env);
    $this->info('In die .env geschrieben: '.implode(', ', array_keys($missing)).'. Den Cron-Link zeigt die Admin-Seite.');

    return 0;
})->purpose('Schlüssel für Web Push und Cron-Token erzeugen, falls sie in der .env fehlen');

Artisan::command('packen:erinnern', function (Reminders $reminders) {
    $this->info($reminders->run().' Erinnerungen verschickt.');
})->purpose('Erinnerungen per Push verschicken – Vorabend, Abfahrtstag, Rückblick (stündlich aufrufen)');
