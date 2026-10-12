<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Evento;
use App\Models\User;
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
}
