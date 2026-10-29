<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AccountService;
use App\Services\AvatarService;
use App\Services\SessionLogin;
use App\Support\WebPush;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
            'apiTokens' => $request->user()->apiTokens()->latest()->get(),
            'pushKey' => WebPush::publicKey(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:100']]);

        $request->user()->update([
            'name' => trim($data['name']),
            'email_notifications' => $request->boolean('email_notifications'),
        ]);

        return redirect()->route('profile.edit')->with('status', 'Gespeichert.');
    }

    /** Neues Bild als zugeschnittene Data-URL; ein leerer Wert entfernt das Bild. */
    public function avatar(Request $request, AvatarService $avatars): RedirectResponse
    {
        $data = $request->validate(['avatar' => ['nullable', 'string', 'max:'.(int) (AvatarService::MAX_BYTES * 1.4)]]);

        if (blank($data['avatar'] ?? null)) {
            $avatars->delete($request->user());

            return redirect()->route('profile.edit')->with('status', 'Bild entfernt.');
        }

        if (! $avatars->store($request->user(), $data['avatar'])) {
            return back()->withErrors(['avatar' => 'Das Bild konnte nicht gelesen werden. Bitte JPG, PNG oder WebP verwenden.']);
        }

        return redirect()->route('profile.edit')->with('status', 'Bild gespeichert.');
    }

    /** Benutzerbilder sehen nur angemeldete Personen. Der Dateiname wechselt mit jedem Bild. */
    public function showAvatar(User $user): StreamedResponse
    {
        $disk = Storage::disk(AvatarService::DISK);
        abort_if($user->avatar_path === null || ! $disk->exists($user->avatar_path), 404);

        return $disk->response($user->avatar_path, headers: ['Cache-Control' => 'private, max-age=31536000, immutable']);
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
