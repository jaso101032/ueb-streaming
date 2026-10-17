<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Evento;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VideoViewTest extends TestCase
{
    use RefreshDatabase;

    private function event(string $status = 'finalizado', ?string $videoUrl = 'https://example.org/video.mp4'): Evento
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $category = Categoria::create(['nombre' => 'General', 'slug' => 'general']);

        return Evento::create([
            'titulo' => 'Video de prueba', 'slug' => 'video-prueba', 'tipo_evento' => 'Conferencia',
            'categoria_id' => $category->id, 'organizador_id' => $admin->id,
            'fecha_inicio' => now()->subHour(), 'estado' => $status, 'video_url' => $videoUrl,
        ]);
    }

    public function test_video_views_count_once_per_browser_per_day_and_do_not_count_page_loads(): void
    {
        $event = $this->event();
        $first = '11111111-1111-4111-8111-111111111111';
        $second = '22222222-2222-4222-8222-222222222222';

        $this->get(route('eventos.show', $event))->assertOk()->assertViewHas('videoViews', 0);
        $this->assertDatabaseCount('event_video_views', 0);
        $this->postJson(route('eventos.video-views', $event), ['viewer' => $first])->assertOk()->assertJsonPath('views', 1);
        $this->postJson(route('eventos.video-views', $event), ['viewer' => $first])->assertOk()->assertJsonPath('views', 1);
        $this->postJson(route('eventos.video-views', $event), ['viewer' => $second])->assertOk()->assertJsonPath('views', 2);
        $this->get(route('eventos.show', $event))->assertOk()->assertViewHas('videoViews', 2);
        $this->travel(1)->days();
        $this->postJson(route('eventos.video-views', $event), ['viewer' => $first])->assertOk()->assertJsonPath('views', 3);
    }

    public function test_live_views_are_recorded_only_when_the_live_stream_exists(): void
    {
        $live = $this->event('en_vivo');
        $viewer = '11111111-1111-4111-8111-111111111111';
        $this->postJson(route('eventos.video-views', $live), ['viewer' => $viewer])->assertStatus(409);
        $live->transmision()->create(['stream_key' => 'live-view-test']);
        $this->postJson(route('eventos.video-views', $live), ['viewer' => $viewer])->assertOk()->assertJsonPath('views', 1);
        $live->update(['estado' => 'cancelado']);
        $this->postJson(route('eventos.video-views', $live), ['viewer' => $viewer])->assertStatus(409);
        $live->update(['estado' => 'finalizado', 'video_url' => null]);
        $this->postJson(route('eventos.video-views', $live), ['viewer' => $viewer])->assertStatus(409);
        $this->assertDatabaseCount('event_video_views', 1);
    }

    public function test_scheduled_video_counts_only_during_or_after_its_emission(): void
    {
        $event = $this->event('programado', null);
        $event->update(['fecha_inicio' => now()->addMinutes(10)]);
        $event->scheduledVideo()->create([
            'path' => 'scheduled-videos/example.mp4', 'duration' => 60,
            'processing_status' => 'ready', 'ready_at' => now(),
        ]);
        $viewer = '11111111-1111-4111-8111-111111111111';

        $this->postJson(route('eventos.video-views', $event), ['viewer' => $viewer])->assertStatus(409);
        $this->travel(11)->minutes();
        $this->postJson(route('eventos.video-views', $event), ['viewer' => $viewer])->assertOk()->assertJsonPath('views', 1);
    }
}
