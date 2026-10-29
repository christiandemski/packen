<?php

namespace App\Http\Controllers;

use App\Models\PushSubscription;
use App\Services\Reminders;
use App\Support\WebPush;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/** Push-Erinnerungen: Geräte an- und abmelden, Inhalte für den Service Worker, täglicher Auslöser. */
class PushController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        abort_unless(WebPush::enabled(), 404);
        $data = $request->validate(['endpoint' => ['required', 'url:https', 'max:1000']]);

        PushSubscription::updateOrCreate(
            ['endpoint_hash' => PushSubscription::hash($data['endpoint'])],
            ['user_id' => $request->user()->id, 'endpoint' => $data['endpoint'], 'user_agent' => Str::limit((string) $request->userAgent(), 200, '')],
        );

        return response()->json(['active' => true], 201);
    }

    public function destroy(Request $request): JsonResponse
    {
        $data = $request->validate(['endpoint' => ['required', 'string', 'max:1000']]);

        PushSubscription::where(['user_id' => $request->user()->id, 'endpoint_hash' => PushSubscription::hash($data['endpoint'])])->delete();

        return response()->json(['active' => false]);
    }

    /** Probe-Nachricht an alle eigenen Geräte. */
    public function test(Request $request): JsonResponse
    {
        abort_unless(WebPush::enabled(), 404);
        $user = $request->user();

        Cache::put("push-test:{$user->id}", true, now()->addMinutes(10));
        $sent = PushSubscription::where('user_id', $user->id)->get()->filter(fn ($device) => WebPush::send($device))->count();

        return response()->json(['sent' => $sent]);
    }

    /** Der Service Worker fragt nach einem Push, was er anzeigen soll. */
    public function messages(Request $request, Reminders $reminder): JsonResponse
    {
        return response()->json(['data' => $reminder->messagesFor($request->user())])->header('Cache-Control', 'no-store');
    }

    /** Stündlicher Auslöser (Plesk „URL abrufen“); was gerade dran ist, entscheidet Reminders. */
    public function cron(Request $request, Reminders $reminder): JsonResponse
    {
        $token = (string) config('services.cron.token');
        abort_if($token === '' || ! hash_equals($token, (string) $request->query('token')), 403);

        return response()->json(['sent' => $reminder->run()]);
    }
}
