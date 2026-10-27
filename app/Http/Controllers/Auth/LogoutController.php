<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\SessionLogin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LogoutController extends Controller
{
    public function __invoke(Request $request, SessionLogin $sessions): RedirectResponse
    {
        $sessions->logout($request);

        return redirect()->route('login');
    }
}
