<?php

namespace App\Http\Controllers;

use App\Models\Trip;
use App\Services\InvitationService;
use App\Services\MagicLinkService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** Admin lädt eine neue Person ein, die nach dem Annehmen direkt in dieser Reise landet. */
class TripInvitationController extends Controller
{
    public function store(Request $request, Trip $trip, InvitationService $invitations): RedirectResponse
    {
        Gate::authorize('admin');
        Gate::authorize('update', $trip);
        abort_unless($trip->isGroup(), 422);

        $request->merge(['email' => MagicLinkService::normalize((string) $request->input('email'))]);
        $data = $request->validateWithBag('invite', [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
        ], ['email.unique' => 'Diese Person hat schon ein Konto – wähle sie oben bei den Mitreisenden aus.']);

        $invitations->invite($request->user(), $data['name'], $data['email'], $trip);

        return redirect()->route('trips.edit', $trip)->with('status', "Einladung an {$data['email']} verschickt – nach dem Annehmen ist die Person in dieser Reise.");
    }
}
