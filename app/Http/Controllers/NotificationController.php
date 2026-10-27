<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    /** Liste der letzten Hinweise; beim Ansehen gelten alle als gelesen. */
    public function index(Request $request): View
    {
        $user = $request->user();
        $notifications = $user->notifications()->latest()->limit(50)->get();
        $unreadIds = $notifications->whereNull('read_at')->pluck('id');

        $user->unreadNotifications()->update(['read_at' => now()]);

        return view('notifications.index', ['notifications' => $notifications, 'unreadIds' => $unreadIds]);
    }
}
