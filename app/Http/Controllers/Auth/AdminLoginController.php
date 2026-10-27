<?php

namespace App\Http\Controllers\Auth;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\MagicLinkService;
use App\Services\SessionLogin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

/** Lokaler Admin-Login als Übergang, bis BearID angebunden ist. */
class AdminLoginController extends Controller
{
    public function create(): View
    {
        return view('auth.admin-login');
    }

    public function store(Request $request, SessionLogin $sessions): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $email = MagicLinkService::normalize($credentials['email']);
        $key = 'admin-login:'.hash('sha256', $email.'|'.$request->ip());

        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()->onlyInput('email')->withErrors([
                'email' => 'Zu viele Versuche. Bitte warte '.RateLimiter::availableIn($key).' Sekunden.',
            ]);
        }

        $user = User::where('email', $email)->where('role', Role::Admin)->where('active', true)->first();

        if ($user?->password === null || ! Hash::check($credentials['password'], $user->password)) {
            RateLimiter::hit($key, 60);

            return back()->onlyInput('email')->withErrors(['email' => 'Diese Zugangsdaten sind ungültig.']);
        }

        RateLimiter::clear($key);
        $sessions->login($request, $user);

        return redirect()->intended(route('home'));
    }
}
