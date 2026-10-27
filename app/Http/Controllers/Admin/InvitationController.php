<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Invitation;
use App\Services\InvitationService;
use App\Services\MagicLinkService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InvitationController extends Controller
{
    public function __construct(private readonly InvitationService $invitations) {}

    public function store(Request $request): RedirectResponse
    {
        $request->merge(['email' => MagicLinkService::normalize((string) $request->input('email'))]);

        $data = $request->validateWithBag('invite', [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
        ], ['email.unique' => 'Für diese Adresse gibt es schon ein Konto.']);

        $this->invitations->invite($request->user(), $data['name'], $data['email']);

        return redirect()->route('admin.index')->with('status', "Einladung an {$data['email']} verschickt.");
    }

    public function resend(Invitation $invitation): RedirectResponse
    {
        abort_if($invitation->accepted_at !== null, 404);

        $this->invitations->resend($invitation);

        return redirect()->route('admin.index')->with('status', "Einladung an {$invitation->email} erneut verschickt.");
    }

    public function destroy(Invitation $invitation): RedirectResponse
    {
        abort_if($invitation->accepted_at !== null, 404);

        $invitation->delete();

        return redirect()->route('admin.index')->with('status', 'Einladung zurückgezogen.');
    }
}
