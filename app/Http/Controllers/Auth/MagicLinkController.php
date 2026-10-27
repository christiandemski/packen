<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\MagicLinkService;
use App\Services\SessionLogin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

class MagicLinkController extends Controller
{
    /** Erlaubte Anfragen je 15 Minuten. */
    public const MAX_PER_EMAIL = 5;

    public const MAX_PER_IP = 20;

    public function __construct(private readonly MagicLinkService $magicLinks) {}

    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email', 'max:255']]);

        $email = MagicLinkService::normalize((string) $request->input('email'));
        $limits = [
            'magic-link:email:'.hash('sha256', $email) => self::MAX_PER_EMAIL,
            'magic-link:ip:'.$request->ip() => self::MAX_PER_IP,
        ];

        foreach ($limits as $key => $max) {
            if (RateLimiter::tooManyAttempts($key, $max)) {
                $minutes = max(1, (int) ceil(RateLimiter::availableIn($key) / 60));

                return back()->withInput()->withErrors([
                    'email' => "Zu viele Anfragen. Bitte versuche es in {$minutes} Minuten erneut.",
                ]);
            }
        }

        foreach (array_keys($limits) as $key) {
            RateLimiter::hit($key, 15 * 60);
        }

        $this->magicLinks->send($email, $request->ip());

        return redirect()->route('login')->with('sent', $email);
    }

    /** Bestätigungsseite – Mail-Scanner, die Links vorab öffnen, verbrauchen den Token so nicht. */
    public function show(string $token): View
    {
        return view('auth.magic-link', [
            'token' => $token,
            'loginToken' => $this->magicLinks->find($token),
        ]);
    }

    public function consume(Request $request, string $token, SessionLogin $sessions): RedirectResponse
    {
        $user = $this->magicLinks->consume($token);

        if ($user === null) {
            return redirect()->route('login')->withErrors([
                'email' => 'Der Link ist abgelaufen oder wurde schon verwendet. Fordere einfach einen neuen an.',
            ]);
        }

        $sessions->login($request, $user);

        return redirect()->intended(route('home'));
    }
}
