<?php

namespace App\Http\Controllers;

use App\Services\AccountService;
use App\Services\SessionLogin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('profile.edit', ['user' => $request->user()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:100']]);

        $request->user()->update(['name' => trim($data['name'])]);

        return redirect()->route('profile.edit')->with('status', 'Gespeichert.');
    }

    public function destroy(Request $request, AccountService $accounts, SessionLogin $sessions): RedirectResponse
    {
        Gate::authorize('deleteOwnAccount', $request->user());

        $request->validate(['confirm' => ['accepted']], ['confirm.accepted' => 'Bitte bestätige, dass du dein Konto löschen möchtest.']);

        $user = $request->user();
        $sessions->logout($request);
        $accounts->delete($user);

        return redirect()->route('login')->with('status', 'Dein Konto wurde gelöscht.');
    }
}
