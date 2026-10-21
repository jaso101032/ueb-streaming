<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AudienceNotificationController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        return response()->json([
            'items' => $user->notifications()->latest()->limit(30)->get(['id', 'data', 'read_at', 'created_at']),
            'unread' => $user->unreadNotifications()->count(),
            'preferences' => ['live' => (bool) $user->notify_live, 'videos' => (bool) $user->notify_videos],
            'pushEndpoints' => DB::table('push_subscriptions')->where('user_id', $user->id)->pluck('endpoint'),
            'pushKey' => filled(config('audience_notifications.vapid_subject')) && filled(config('audience_notifications.vapid_private_key'))
                ? config('audience_notifications.vapid_public_key') : null,
        ]);
    }

    public function preferences(Request $request)
    {
        $data = $request->validate(['live' => ['required', 'boolean'], 'videos' => ['required', 'boolean']]);
        $request->user()->forceFill(['notify_live' => $data['live'], 'notify_videos' => $data['videos']])->save();
        return response()->json(['ok' => true]);
    }

    public function read(Request $request, string $id)
    {
        $request->user()->notifications()->whereKey($id)->update(['read_at' => now()]);
        return response()->json(['ok' => true]);
    }

    public function subscribe(Request $request)
    {
        abort_unless(filled(config('audience_notifications.vapid_public_key')) && filled(config('audience_notifications.vapid_private_key')), 503);
        $data = $request->validate([
            'endpoint' => ['required', 'string', 'max:2048'],
            'keys.p256dh' => ['required', 'string', 'max:200'],
            'keys.auth' => ['required', 'string', 'max:200'],
        ]);
        $url = parse_url($data['endpoint']);
        $host = strtolower($url['host'] ?? '');
        $trusted = in_array($host, ['fcm.googleapis.com', 'updates.push.services.mozilla.com', 'web.push.apple.com'], true)
            || str_ends_with($host, '.push.apple.com')
            || str_ends_with($host, '.notify.windows.com');
        $key = base64_decode(strtr($data['keys']['p256dh'], '-_', '+/'), true);
        $auth = base64_decode(strtr($data['keys']['auth'], '-_', '+/'), true);
        if (! $trusted || ! preg_match('/^[a-z0-9.-]+$/D', $host) || ($url['scheme'] ?? '') !== 'https'
            || isset($url['port']) || isset($url['user']) || isset($url['pass'])
            || ! preg_match('/^[A-Za-z0-9_-]+$/D', $data['keys']['p256dh'])
            || ! preg_match('/^[A-Za-z0-9_-]+$/D', $data['keys']['auth'])
            || strlen((string) $key) !== 65 || strlen((string) $auth) !== 16) {
            throw ValidationException::withMessages(['endpoint' => 'Suscripción del navegador no válida.']);
        }
        DB::table('push_subscriptions')->updateOrInsert(['endpoint_hash' => hash('sha256', $data['endpoint'])], [
            'user_id' => $request->user()->id,
            'endpoint' => $data['endpoint'],
            'public_key' => $data['keys']['p256dh'],
            'auth_token' => $data['keys']['auth'],
            'content_encoding' => 'aes128gcm',
            'updated_at' => now(),
        ]);
        return response()->json(['ok' => true]);
    }

    public function unsubscribe(Request $request)
    {
        $data = $request->validate(['endpoint' => ['required', 'string', 'max:2048']]);
        DB::table('push_subscriptions')->where('user_id', $request->user()->id)
            ->where('endpoint_hash', hash('sha256', $data['endpoint']))->delete();
        return response()->json(['ok' => true]);
    }
}
