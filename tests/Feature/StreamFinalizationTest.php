<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Evento;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class StreamFinalizationTest extends TestCase
{
    use RefreshDatabase;

    private function event(): Evento
    {
        $user = User::factory()->create(['role' => 'admin']);
        $category = Categoria::create(['nombre' => 'General', 'slug' => 'general']);
        $event = Evento::create(['titulo' => 'Prueba', 'slug' => 'prueba', 'tipo_evento' => 'Conferencias',
            'categoria_id' => $category->id, 'organizador_id' => $user->id,
            'fecha_inicio' => now()->subHour(), 'estado' => 'en_vivo']);
        $event->transmision()->create(['stream_key' => 'ueb_test', 'rtmp_server' => 'rtmp://localhost/live',
            'hls_url' => 'http://localhost/hls/ueb_test.m3u8', 'activo' => true]);
        config(['streaming.callback_token' => str_repeat('a', 64), 'streaming.control_url' => 'http://127.0.0.1:8091/control']);
        Http::preventStrayRequests();
        return $event;
    }

    public function test_operator_can_stop_only_the_selected_stream_and_repeat_safely(): void
    {
        $event = $this->event();
        Http::fake(['127.0.0.1:8091/*' => Http::response('1')]);
        $this->actingAs(User::factory()->create(['role' => 'operador']));
        $this->post(route('streaming.finalizar', $event))->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->assertSame('finalizado', $event->fresh()->estado);
        $stream = $event->transmision()->first();
        $this->assertFalse($stream->activo);
        $this->assertNotNull($stream->finalizado_en);
        Http::assertSent(fn ($request) => $request['app'] === 'live' && $request['name'] === 'ueb_test');
        $this->post(route('streaming.finalizar', $event))->assertSessionHasNoErrors();
        Http::assertSentCount(1);
        $this->assertEquals($stream->finalizado_en, $stream->fresh()->finalizado_en);
        $this->get(route('streaming.estado', $event))->assertJson(['en_vivo' => false, 'finalizado' => true]);
    }

    public function test_server_failure_does_not_finalize_the_event(): void
    {
        $event = $this->event();
        Http::fake(['*' => Http::response('Not configured', 404)]);
        $this->actingAs(User::where('role', 'admin')->first());
        $this->post(route('streaming.finalizar', $event))->assertSessionHasErrors('streaming');
        $this->assertSame('en_vivo', $event->fresh()->estado);
        $this->assertNull($event->transmision()->first()->finalizado_en);
    }

    public function test_viewers_and_guests_cannot_stop_streams(): void
    {
        $event = $this->event();
        $this->post(route('streaming.finalizar', $event))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create(['role' => 'usuario']));
        $this->post(route('streaming.finalizar', $event))->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_callbacks_require_token_and_reject_finished_and_unknown_streams(): void
    {
        $route = app('router')->getRoutes()->getByName('streaming.authorize');
        $this->assertNotContains(\Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class,
            app('router')->gatherRouteMiddleware($route));
        $event = $this->event();
        $payload = ['app' => 'live', 'name' => 'ueb_test'];
        $this->post('/streaming/hooks/authorize', $payload)->assertForbidden();
        $url = '/streaming/hooks/authorize?token='.str_repeat('a', 64);
        $this->post($url, $payload)->assertNoContent();
        $this->post($url, ['app' => 'live', 'name' => 'unknown'])->assertForbidden();
        $event->update(['estado' => 'finalizado']);
        $this->post($url, $payload)->assertForbidden();
        $this->actingAs(User::where('role', 'admin')->first());
        $this->post(route('streaming.generar.clave', $event))->assertStatus(422);
    }

    public function test_control_and_detail_show_the_finish_action(): void
    {
        $event = $this->event();
        $this->actingAs(User::where('role', 'admin')->first());
        $this->get(route('streaming.control'))->assertOk()->assertSee('Finalizar transmisión');
        $this->get(route('admin.eventos.show', $event))->assertOk()->assertSee('Finalizar transmisión');
    }
}
