<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Kennzeichnet jede Antwort der App. Service Worker und Offline-Warteschlange erkennen so,
 * ob eine Antwort wirklich von uns kommt – oder von einem Router, Proxy oder einer
 * WLAN-Vorschaltseite. Fremde Antworten gelten als „kein Netz“.
 */
class MarkAppResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $response->headers->set('X-Packen', '1');

        return $response;
    }
}
