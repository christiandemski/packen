<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function __construct(private readonly AccountService $accounts) {}

    public function block(User $user): RedirectResponse
    {
        Gate::authorize('manage', $user);

        $this->accounts->block($user);

        return redirect()->route('admin.index')->with('status', "{$user->name} ist gesperrt.");
    }

    public function unblock(User $user): RedirectResponse
    {
        Gate::authorize('manage', $user);

        $this->accounts->unblock($user);

        return redirect()->route('admin.index')->with('status', "{$user->name} ist wieder freigeschaltet.");
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('manage', $user);

        $request->validateWithBag('delete-'.$user->id, ['confirm_email' => ['required', Rule::in([$user->email])]], [
            'confirm_email.in' => 'Zur Bestätigung bitte genau die E-Mail-Adresse eingeben.',
        ]);

        $name = $user->name;
        $this->accounts->delete($user);

        return redirect()->route('admin.index')->with('status', "Das Konto von {$name} wurde gelöscht.");
    }
}
