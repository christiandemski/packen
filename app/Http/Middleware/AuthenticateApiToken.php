<?php

namespace App\Http\Middleware;

use App\Services\ApiTokenService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** API-Zugang per „Authorization: Bearer packen_…“; ohne Sitzung und Cookies. */
class AuthenticateApiToken
{
    public function __construct(private readonly ApiTokenService $tokens) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = $this->tokens->authenticate($request->bearerToken());

        if ($token === null) {
            return response()->json(['message' => 'Ungültiger oder fehlender API-Token.'], 401);
        }

        Auth::setUser($token->user);
        $request->setUserResolver(fn () => $token->user);

        return $next($request);
    }
}
