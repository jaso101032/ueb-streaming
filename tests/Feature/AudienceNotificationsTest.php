<?php

namespace Tests\Feature;

use App\Jobs\DeliverAudienceAnnouncement;
use App\Jobs\FanoutAudienceAnnouncement;
use App\Models\Categoria;
use App\Models\Evento;
use App\Models\User;
use App\Services\AudienceAnnouncements;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AudienceNotificationsTest extends TestCase
{
    use RefreshDatabase;

    private function event(User $owner): Evento
    {
        $category = Categoria::create(['nombre' => 'General', 'slug' => 'general']);
        return Evento::create([
            'titulo' => 'Seminario UEB', 'slug' => 'seminario-ueb', 'organizador_id' => $owner->id,
            'categoria_id' => $category->id, 'tipo_evento' => 'Seminario',
            'estado' => 'en_vivo', 'fecha_inicio' => now(),
        ]);
    }

    public function test_only_authenticated_users_can_read_and_change_preferences(): void
    {
        $this->get(route('notifications.index'))->assertRedirect();
        $user = User::factory()->create();
        $this->actingAs($user)->get(route('notifications.index'))->assertOk()
            ->assertJsonPath('preferences.live', true)
            ->assertJsonPath('unread', 0);
        $this->putJson(route('notifications.preferences'), ['live' => false, 'videos' => true])->assertOk();
        $this->assertFalse((bool) $user->fresh()->notify_live);
    }

    public function test_announcement_is_unique_and_delivery_respects_preferences(): void
    {
        Queue::fake();
        $owner = User::factory()->create();
        $muted = User::factory()->create(['notify_live' => false]);
        $event = $this->event($owner);
        app(AudienceAnnouncements::class)->publish($event, 'live');
        app(AudienceAnnouncements::class)->publish($event, 'live');
        $this->assertDatabaseCount('audience_announcements', 1);
        Queue::assertPushed(FanoutAudienceAnnouncement::class, 1);

        $noticeId = DB::table('audience_announcements')->value('id');
        $job = new DeliverAudienceAnnouncement($noticeId, [$owner->id, $muted->id]);
        $job->handle();
        $job->handle();
        $this->assertDatabaseCount('notifications', 1);
        $this->actingAs($owner)->get(route('notifications.index'))->assertJsonPath('unread', 1);
        $id = DB::table('notifications')->value('id');
        $this->actingAs($muted)->patchJson(route('notifications.read', $id))->assertOk();
        $this->assertNull(DB::table('notifications')->where('id', $id)->value('read_at'));
        $this->actingAs($owner)->patchJson(route('notifications.read', $id))->assertOk();
        $this->assertNotNull(DB::table('notifications')->where('id', $id)->value('read_at'));
    }

    public function test_push_endpoint_is_restricted_and_revoked_on_logout(): void
    {
        config(['audience_notifications.vapid_public_key' => 'test-public', 'audience_notifications.vapid_private_key' => 'test-private']);
        $user = User::factory()->create();
        $keys = [
            'p256dh' => rtrim(strtr(base64_encode(random_bytes(65)), '+/', '-_'), '='),
            'auth' => rtrim(strtr(base64_encode(random_bytes(16)), '+/', '-_'), '='),
        ];
        $this->actingAs($user)->postJson(route('notifications.push.subscribe'), [
            'endpoint' => 'https://127.0.0.1/private', 'keys' => $keys,
        ])->assertUnprocessable();
        $this->actingAs($user)->postJson(route('notifications.push.subscribe'), [
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/example', 'keys' => $keys,
        ])->assertOk();
        $this->assertDatabaseCount('push_subscriptions', 1);
        $this->actingAs($user)->post(route('logout'))->assertRedirect('/');
        $this->assertDatabaseCount('push_subscriptions', 0);
    }

    public function test_live_notice_waits_for_a_playable_hls_playlist(): void
    {
        Queue::fake();
        Storage::fake('local');
        config(['streaming.hls_root' => Storage::disk('local')->path('hls')]);
        $event = $this->event(User::factory()->create());
        $event->transmision()->create(['stream_key' => 'ueb_test', 'activo' => true,
            'last_signal_at' => now(), 'recording_status' => 'recording']);
        app(AudienceAnnouncements::class)->publish($event, 'live');
        $id = DB::table('audience_announcements')->value('id');
        (new FanoutAudienceAnnouncement($id))->handle();
        Queue::assertNotPushed(DeliverAudienceAnnouncement::class);

        Storage::disk('local')->put('hls/ueb_test.m3u8', "#EXTM3U\n#EXTINF:2.0,\nueb_test0.ts\n");
        (new FanoutAudienceAnnouncement($id))->handle();
        Queue::assertPushed(DeliverAudienceAnnouncement::class);
    }
}
