<?php

namespace App\Http\Controllers;

use App\Models\ApiToken;
use App\Services\ApiTokenService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Zugänge für andere Apps im Profil anlegen und widerrufen. */
class ApiTokenController extends Controller
{
    public function store(Request $request, ApiTokenService $tokens): RedirectResponse
    {
        $data = $request->validateWithBag('apiToken', ['name' => ['required', 'string', 'max:60']], [], ['name' => 'Name']);

        [, $plain] = $tokens->create($request->user(), $data['name']);

        // Klartext nur dieses eine Mal anzeigen.
        return redirect()->route('profile.edit')->with('api_token', $plain)->with('status', 'Zugang angelegt.');
    }

    public function destroy(Request $request, ApiToken $apiToken): RedirectResponse
    {
        abort_unless($apiToken->user_id === $request->user()->id, 404);

        $apiToken->delete();

        return redirect()->route('profile.edit')->with('status', 'Zugang widerrufen.');
    }
}
