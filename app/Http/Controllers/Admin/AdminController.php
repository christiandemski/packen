<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\View\View;

class AdminController extends Controller
{
    public function __invoke(): View
    {
        return view('admin.index', [
            'users' => User::orderByDesc('role')->orderBy('name')->get(),
            'invitations' => Invitation::open()->with('inviter')->latest()->get(),
        ]);
    }
}
