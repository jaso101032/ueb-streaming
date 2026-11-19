<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Evento;
use App\Models\User;
use App\Services\ViewerPresence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ViewerPresenceTest extends TestCase
{
    use RefreshDatabase;

    private function liveEvent(): Evento
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $category = Categoria::create(['nombre' => 'General', 'slug' => 'general']);
        $event = Evento::create(['titulo' => 'Señal', 'slug' => 'senal', 'tipo_evento' => 'Conferencia', 'categoria_id' => $category->id, 'organizador_id' => $admin->id, 'fecha_inicio' => now(), 'estado' => 'en_vivo']);
        $event->transmision()->create(['stream_key' => 'ueb_viewers', 'rtmp_server' => 'rtmp://localhost/live', 'hls_url' => 'http://localhost/hls/ueb_viewers.m3u8', 'activo' => true]);
        return $event;
    }

    public function test_anonymous_heartbeats_count_each_browser_once(): void
    {
        $event = $this->liveEvent();
        $first = '11111111-1111-4111-8111-111111111111';
        $second = '22222222-2222-4222-8222-222222222222';

        $this->postJson(route('eventos.viewers', $event), ['viewer' => $first])->assertOk()->assertJsonPath('viewers', 1);
        $this->postJson(route('eventos.viewers', $event), ['viewer' => $first])->assertOk()->assertJsonPath('viewers', 1);
        $this->postJson(route('eventos.viewers', $event), ['viewer' => $second])->assertOk()->assertJsonPath('viewers', 2);

        $this->assertDatabaseCount('event_viewers', 2);
        $this->assertSame(2, $event->transmision()->first()->fresh()->espectadores_activos);
    }

    public function test_viewers_who_stop_sending_signals_drop_out_without_anyone_else_pinging(): void
    {
        $event = $this->liveEvent();
        $first = '11111111-1111-4111-8111-111111111111';
        $second = '22222222-2222-4222-8222-222222222222';
        $this->postJson(route('eventos.viewers', $event), ['viewer' => $first])->assertJsonPath('viewers', 1);
        $this->postJson(route('eventos.viewers', $event), ['viewer' => $second])->assertJsonPath('viewers', 2);

        // Pasa casi toda la ventana: siguen conectados.
        $this->travel(ViewerPresence::WINDOW - 5)->seconds();
        $this->assertSame(2, app(ViewerPresence::class)->count($event));

        // Nadie envía señal y la ventana vence: el número baja solo, sin otra señal que lo recalcule.
        $this->travel(10)->seconds();
        $this->assertSame(0, app(ViewerPresence::class)->count($event));
    }

    public function test_a_viewer_who_comes_back_is_counted_again(): void
    {
        $event = $this->liveEvent();
        $viewer = '33333333-3333-4333-8333-333333333333';
        $this->postJson(route('eventos.viewers', $event), ['viewer' => $viewer])->assertJsonPath('viewers', 1);
        $this->travel(ViewerPresence::WINDOW + 5)->seconds();
        $this->assertSame(0, app(ViewerPresence::class)->count($event));
        $this->postJson(route('eventos.viewers', $event), ['viewer' => $viewer])->assertJsonPath('viewers', 1);
    }

    public function test_the_channel_reports_the_live_count_not_a_stale_stored_number(): void
    {
        $event = $this->liveEvent();
        $event->transmision->update(['last_signal_at' => now(), 'iniciado_en' => now(), 'espectadores_activos' => 99]);

        // El valor guardado (99) está desfasado: el canal debe informar los conectados reales.
        $this->getJson(route('channel.state'))->assertOk()->assertJsonPath('mode', 'live')->assertJsonPath('event.viewers', 0);

        $this->postJson(route('eventos.viewers', $event), ['viewer' => '44444444-4444-4444-8444-444444444444'])->assertJsonPath('viewers', 1);
        $this->postJson(route('eventos.viewers', $event), ['viewer' => '55555555-5555-4555-8555-555555555555'])->assertJsonPath('viewers', 2);
        $this->getJson(route('channel.state'))->assertJsonPath('event.viewers', 2)
            ->assertJsonPath('event.viewersUrl', '/eventos/'.$event->id.'/espectadores');

        $this->travel(ViewerPresence::WINDOW + 1)->seconds();
        $event->transmision->update(['last_signal_at' => now()]);
        $this->getJson(route('channel.state'))->assertJsonPath('event.viewers', 0);
    }

    public function test_presence_is_not_counted_once_the_event_is_over(): void
    {
        $event = $this->liveEvent();
        $event->update(['estado' => 'finalizado']);
        $this->postJson(route('eventos.viewers', $event), ['viewer' => '66666666-6666-4666-8666-666666666666'])->assertJsonPath('viewers', 0);
        $this->assertDatabaseCount('event_viewers', 0);
    }
}
