<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\InvitationService;
use App\Services\SessionLogin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InvitationAcceptanceController extends Controller
{
    public function __construct(private readonly InvitationService $invitations) {}

    public function show(string $token): View
    {
        return view('auth.invitation', [
            'token' => $token,
            'invitation' => $this->invitations->find($token),
        ]);
    }

    public function store(Request $request, string $token, SessionLogin $sessions): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:100']]);

        $user = $this->invitations->accept($token, $data['name']);

        if ($user === null) {
            return redirect()->route('invitation.show', $token);
        }

        $sessions->login($request, $user);

        return redirect()->route('home')->with('status', 'Willkommen, '.$user->name.'!');
    }
}
