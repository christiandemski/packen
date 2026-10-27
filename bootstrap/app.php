<?php

use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\MarkAppResponse;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [EnsureUserIsActive::class]);
        $middleware->prepend(MarkAppResponse::class);
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('home'));

        // Nur bekannten Proxies vertrauen, sonst lässt sich die IP für das Rate-Limit per
        // X-Forwarded-For fälschen. Lokal sitzt der DDEV-Router davor, bei Plesk ein nginx
        // auf demselben Server vor Apache.
        $middleware->trustProxies(at: getenv('IS_DDEV_PROJECT') === 'true' ? '*' : ['127.0.0.1', '::1']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
