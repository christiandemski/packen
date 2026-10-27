<?php

namespace App\Http\Middleware;

use App\Services\SessionLogin;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Gesperrte Konten fliegen auch aus noch offenen Sitzungen sofort raus. */
class EnsureUserIsActive
{
    public function __construct(private readonly SessionLogin $sessions) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && ! $user->active) {
            $this->sessions->logout($request);

            return redirect()->route('login')->with('status', 'Dein Konto ist gesperrt.');
        }

        return $next($request);
    }
}
