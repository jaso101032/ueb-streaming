<?php

namespace App\Jobs;

use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

class DeliverAudienceAnnouncement implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $announcementId, public array $userIds) {}

    public function handle(): void
    {
        $notice = DB::table('audience_announcements')->find($this->announcementId);
        if (! $notice) return;
        $column = $notice->kind === 'live' ? 'notify_live' : 'notify_videos';
        $vapid = config('audience_notifications');
        $pushEnabled = filled($vapid['vapid_subject']) && filled($vapid['vapid_public_key']) && filled($vapid['vapid_private_key']);
        $push = null;
        if ($pushEnabled) {
            try {
                $push = new WebPush(['VAPID' => [
                    'subject' => $vapid['vapid_subject'],
                    'publicKey' => $vapid['vapid_public_key'],
                    'privateKey' => $vapid['vapid_private_key'],
                ]], ['TTL' => 3600], 8, ['allow_redirects' => false], Log::getLogger());
            } catch (\Throwable $exception) { report($exception); }
        }

        foreach (User::whereIn('id', $this->userIds)->where($column, true)->get(['id']) as $user) {
            // Stable ID makes retrying a queue job safe for the in-app inbox.
            $hash = substr(hash('sha256', $this->announcementId.':'.$user->id), 0, 32);
            $id = substr($hash, 0, 8).'-'.substr($hash, 8, 4).'-4'.substr($hash, 13, 3)
                .'-8'.substr($hash, 17, 3).'-'.substr($hash, 20, 12);
            $created = DB::table('notifications')->insertOrIgnore([
                'id' => $id, 'type' => 'audience', 'notifiable_type' => User::class,
                'notifiable_id' => $user->id,
                'data' => json_encode(['kind' => $notice->kind, 'title' => $notice->title, 'body' => $notice->body, 'url' => $notice->url], JSON_THROW_ON_ERROR),
                'created_at' => now(), 'updated_at' => now(),
            ]);
            if (! $created || ! $push) continue;
            foreach (DB::table('push_subscriptions')->where('user_id', $user->id)->get() as $subscription) {
                try {
                    $push->queueNotification(Subscription::create([
                        'endpoint' => $subscription->endpoint,
                        'publicKey' => $subscription->public_key,
                        'authToken' => $subscription->auth_token,
                        'contentEncoding' => $subscription->content_encoding,
                    ]), json_encode(['title' => $notice->title, 'body' => $notice->body, 'url' => $notice->url], JSON_THROW_ON_ERROR));
                } catch (\Throwable $exception) { report($exception); }
            }
        }
        if (! $push) return;
        try {
            foreach ($push->flush(25) as $report) {
                if ($report->isSubscriptionExpired()) {
                    DB::table('push_subscriptions')->where('endpoint_hash', hash('sha256', $report->getEndpoint()))->delete();
                } elseif (! $report->isSuccess()) {
                    report(new \RuntimeException('Web Push no entregado: '.$report->getReason()));
                }
            }
        } catch (\Throwable $exception) { report($exception); }
    }
}
