<?php

namespace App\Http\Controllers;

use App\Enums\PushKind;
use App\Support\WebPush;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    /**
     * Liste der letzten Hinweise; beim Ansehen gelten alle als gelesen.
     * Mit ?popup=1 nur die Liste für das Popup der Glocke (wird beim Öffnen nachgeladen).
     */
    public function index(Request $request): View
    {
        $user = $request->user();
        $popup = $request->boolean('popup');
        $notifications = $user->notifications()->latest()->limit($popup ? 8 : 50)->get();
        $unreadIds = $notifications->whereNull('read_at')->pluck('id');

        $user->unreadNotifications()->update(['read_at' => now()]);

        return $popup
            ? view('notifications._list', ['notifications' => $notifications, 'unreadIds' => $unreadIds, 'compact' => true])
            : view('notifications.index', ['notifications' => $notifications, 'unreadIds' => $unreadIds]);
    }

    /** Übersicht: was wohin kommt (App, Push, E-Mail). */
    public function settings(Request $request): View
    {
        return view('notifications.settings', [
            'user' => $request->user(),
            'pushKey' => WebPush::publicKey(),
            'devices' => $request->user()->pushSubscriptions()->count(),
        ]);
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $request->user()->update([
            'email_notifications' => $request->boolean('email_notifications'),
            'push_settings' => collect(PushKind::cases())->mapWithKeys(fn (PushKind $kind) => [$kind->value => $request->boolean('push.'.$kind->value)])->all(),
        ]);

        return redirect()->route('notifications.settings')->with('status', 'Gespeichert.');
    }
}
