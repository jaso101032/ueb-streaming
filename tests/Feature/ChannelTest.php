<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Evento;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ChannelTest extends TestCase
{
    use RefreshDatabase;

    private function event(string $title, int $start = 0, ?int $duration = null): Evento
    {
        $user = User::first() ?? User::factory()->create(['role' => 'admin']);
        $category = Categoria::first() ?? Categoria::create(['nombre' => 'General', 'slug' => 'general']);
        $e = Evento::create(['titulo' => $title, 'slug' => $title, 'tipo_evento' => 'Conferencia', 'organizador_id' => $user->id,
            'categoria_id' => $category->id, 'fecha_inicio' => now()->addSeconds($start), 'estado' => 'programado']);
        if ($duration !== null) {
            $e->scheduledVideo()->create(['path' => "scheduled-videos/$title.mp4", 'duration' => $duration]);
        } else {
            $e->transmision()->create(['stream_key' => "ueb_$title", 'rtmp_server' => 'rtmp://localhost/live', 'hls_url' => "http://localhost/hls/$title.m3u8", 'activo' => false]);
        }

        return $e;
    }

    private function signal(Evento $event, string $call = 'publish', string $client = '1'): void
    {
        config(['streaming.callback_token' => 'channel-test']);
        $this->post('/streaming/hooks/authorize?token=channel-test', ['app' => 'live', 'name' => $event->transmision->stream_key, 'call' => $call, 'clientid' => $client])->assertNoContent();
    }

    private function stop(Evento $event, string $client = '1'): void
    {
        $this->post('/streaming/hooks/disconnected?token=channel-test', ['app' => 'live', 'name' => $event->transmision->stream_key, 'clientid' => $client])->assertNoContent();
    }

    public function test_queue_advances_without_overlap_and_sums_all_available_videos(): void
    {
        $this->freezeTime();
        $a = $this->event('first', 0, 60);
        $b = $this->event('second', 0, 120);
        $cancelled = $this->event('cancelled', 500, 50);
        $cancelled->update(['estado' => 'cancelado']);
        $deleted = $this->event('deleted', 600, 70);
        $deleted->delete();
        $this->get(route('channel.state'))->assertOk()->assertJsonPath('event.id', $a->id)->assertJsonPath('summary.totalSeconds', 180)->assertJsonPath('summary.count', 2);
        $this->travel(65)->seconds();
        $this->get(route('channel.state'))->assertJsonPath('mode', 'scheduled')->assertJsonPath('event.id', $b->id)->assertJsonPath('playback.position', 5)->assertJsonPath('summary.remainingSeconds', 115);
        $this->travel(115)->seconds();
        $this->get(route('channel.state'))->assertJsonPath('mode', 'idle')->assertJsonPath('summary.remainingSeconds', 0);
    }

    public function test_live_pauses_and_disconnect_resumes_exact_position_for_all_viewers(): void
    {
        $this->freezeTime();
        $video = $this->event('program', 0, 120);
        $live = $this->event('live');
        $this->travel(20)->seconds();
        $this->signal($live);
        $this->travel(15)->seconds();
        $this->signal($live, 'update_publish');
        $this->get(route('channel.state'))->assertJsonPath('mode', 'live')->assertJsonPath('event.id', $live->id)->assertJsonPath('resumeTitle', 'program');
        $this->get(route('scheduled.state', $video))->assertJsonPath('state', 'paused')->assertJsonPath('position', 20);
        $this->travel(10)->seconds();
        $this->stop($live);
        $this->get(route('channel.state'))->assertJsonPath('mode', 'scheduled')->assertJsonPath('playback.position', 20);
        $this->travel(5)->seconds();
        foreach (range(1, 2) as $viewer) {
            $this->get(route('channel.state'))->assertJsonPath('playback.position', 25);
        }
    }

    public function test_stale_signal_expires_and_overlapping_lives_only_pause_once(): void
    {
        $this->freezeTime();
        $this->event('program', 0, 200);
        $a = $this->event('a');
        $b = $this->event('b');
        $this->travel(10)->seconds();
        $this->signal($a);
        $this->travel(10)->seconds();
        $this->signal($b);
        $this->travel(10)->seconds();
        $this->stop($a);
        $this->get(route('channel.state'))->assertJsonPath('event.id', $b->id);
        $this->travel(25)->seconds(); // B's last heartbeat expired at t=50.
        $this->get(route('channel.state'))->assertJsonPath('mode', 'scheduled')->assertJsonPath('playback.position', 15);
    }

    public function test_old_disconnect_does_not_interrupt_a_new_publisher_and_manual_finish_resumes(): void
    {
        $this->freezeTime();
        $this->event('program', 0, 200);
        $live = $this->event('live');
        $this->signal($live);
        $this->travel(31)->seconds();
        $this->signal($live, 'publish', '2');
        $this->stop($live, '1');
        $this->get(route('channel.state'))->assertJsonPath('mode', 'live');
        Http::fake(['*' => Http::response('1')]);
        $this->actingAs(User::first())->post(route('streaming.finalizar', $live))->assertSessionHasNoErrors();
        $this->get(route('channel.state'))->assertJsonPath('mode', 'scheduled')->assertJsonPath('playback.position', 1);
    }

    public function test_durations_include_all_pages_and_do_not_count_waiting_gaps(): void
    {
        $this->freezeTime();
        foreach (range(1, 15) as $i) {
            $this->event("video$i", $i * 100, 60);
        }
        $this->actingAs(User::first());
        $this->get(route('admin.scheduled.index'))->assertOk()->assertSee('&quot;totalSeconds&quot;:900', false);
        $this->get(route('channel.state'))->assertJsonPath('mode', 'idle')->assertJsonPath('summary.totalSeconds', 900);
    }

    public function test_channel_keeps_five_upcoming_items_and_exposes_the_full_agenda(): void
    {
        $this->freezeTime();
        foreach (range(1, 8) as $index) {
            $this->event("agenda-$index", $index * 86400, 60);
        }

        $response = $this->get(route('channel.state'))->assertOk();
        $response->assertJsonCount(5, 'next')
            ->assertJsonCount(8, 'agenda')
            ->assertJsonPath('agenda.7.title', 'agenda-8');
    }

    public function test_archive_deletion_does_not_rewind_the_current_program(): void
    {
        $this->freezeTime();
        $old = $this->event('old', 0, 20);
        $next = $this->event('next', 0, 60);
        $this->travel(30)->seconds();
        $old->delete();
        $this->get(route('channel.state'))->assertJsonPath('event.id', $next->id)->assertJsonPath('playback.position', 10)->assertJsonPath('summary.totalSeconds', 60);
    }

    public function test_deleted_future_slot_stays_removed_after_its_original_start(): void
    {
        $this->freezeTime();
        $removed = $this->event('removed', 10, 120);
        $next = $this->event('next', 10, 60);
        $removed->delete();
        $this->travel(20)->seconds();
        $this->get(route('channel.state'))->assertJsonPath('event.id', $next->id)->assertJsonPath('playback.position', 10);
    }

    public function test_live_delay_keeps_program_out_of_archive_until_its_actual_end(): void
    {
        $this->freezeTime();
        $video = $this->event('program', 0, 30);
        $live = $this->event('live');
        $this->travel(10)->seconds();
        $this->signal($live);
        $this->travel(20)->seconds();
        $this->signal($live, 'update_publish');
        $this->travel(10)->seconds();
        $this->get(route('scheduled.state', $video))->assertJsonPath('state', 'paused')->assertJsonPath('position', 10);
        $props = $this->reactProps($this->get(route('eventos.index', ['filtro' => 'anteriores'])));
        $this->assertCount(0, $props['events']);
        $this->stop($live);
        $this->travel(20)->seconds();
        $this->get(route('scheduled.state', $video))->assertJsonPath('state', 'ended');
    }
}
