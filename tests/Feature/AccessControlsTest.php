<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Evento;
use App\Models\Transmision;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Controles transversales exigidos para la versión evaluada: separación de la
 * clave de OBS, visibilidad, autorización editorial, auditoría y permisos.
 */
class AccessControlsTest extends TestCase
{
    use RefreshDatabase;

    private string $hlsRoot;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->hlsRoot = sys_get_temp_dir().'/uebtv-hls-test-'.Str::uuid();
        mkdir($this->hlsRoot, 0700, true);
        config(['streaming.hls_root' => $this->hlsRoot, 'streaming.x_accel_media' => false]);
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->hlsRoot.'/*') ?: []);
        @rmdir($this->hlsRoot);
        parent::tearDown();
    }

    private function liveEvent(string $visibility = 'publico', string $key = 'ueb_secretkey123'): Evento
    {
        $category = Categoria::firstOrCreate(['slug' => 'general'], ['nombre' => 'General']);
        $event = Evento::create(['titulo' => 'Directo '.$visibility, 'slug' => 'directo-'.Str::uuid(), 'tipo_evento' => 'Conferencia',
            'categoria_id' => $category->id, 'organizador_id' => $this->admin->id, 'fecha_inicio' => now(),
            'estado' => 'en_vivo', 'visibilidad' => $visibility]);
        $event->transmision()->create(['stream_key' => $key, 'rtmp_server' => 'rtmp://localhost/live',
            'activo' => true, 'last_signal_at' => now(), 'iniciado_en' => now()]);
        file_put_contents($this->hlsRoot.'/'.$key.'.m3u8', "#EXTM3U\n#EXT-X-VERSION:3\n#EXT-X-TARGETDURATION:2\n#EXT-X-MEDIA-SEQUENCE:7\n#EXTINF:2.000,\n{$key}-7.ts\n#EXTINF:2.000,\n{$key}-8.ts\n");
        file_put_contents($this->hlsRoot.'/'.$key.'-7.ts', 'segment-seven');

        return $event->fresh('transmision');
    }

    public function test_viewers_receive_a_public_playback_id_and_never_the_obs_key(): void
    {
        $event = $this->liveEvent();
        $stream = $event->transmision;
        $this->assertMatchesRegularExpression('/^[a-z0-9]{32}$/', $stream->playback_id);
        $this->assertStringNotContainsString($stream->stream_key, $stream->playback_id);

        $page = $this->get(route('eventos.show', $event))->assertOk()->getContent();
        $this->assertStringContainsString('/directo/'.$stream->playback_id.'.m3u8', $page);
        $this->assertStringNotContainsString($stream->stream_key, $page);
        $channel = $this->getJson(route('channel.state'))->assertOk()->assertJsonPath('mode', 'live');
        $this->assertStringNotContainsString($stream->stream_key, $channel->getContent());
        $catalog = $this->get(route('eventos.index'))->assertOk()->getContent();
        $this->assertStringNotContainsString($stream->stream_key, $catalog);

        $playlist = $this->get($stream->playbackUrl())->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.apple.mpegurl')->getContent();
        $this->assertStringNotContainsString($stream->stream_key, $playlist);
        $this->assertStringContainsString($stream->playback_id."/7.ts\n", $playlist);
        $this->assertStringContainsString('#EXT-X-MEDIA-SEQUENCE:7', $playlist);

        $segment = $this->get('/directo/'.$stream->playback_id.'/7.ts')->assertOk();
        $this->assertSame('segment-seven', $segment->streamedContent());
        $this->get('/directo/'.$stream->playback_id.'/8.ts')->assertNotFound();
        config(['streaming.x_accel_media' => true]);
        $this->get('/directo/'.$stream->playback_id.'/7.ts')->assertOk()
            ->assertHeader('X-Accel-Redirect', '/_protected_hls/'.$stream->stream_key.'-7.ts');

        // The OBS key is not a valid playback identifier.
        $this->get('/directo/'.$stream->stream_key.'.m3u8')->assertNotFound();
        $this->get('/directo/'.Str::lower(Str::random(32)).'.m3u8')->assertNotFound();
        // Operators still see the key they must type in OBS.
        $this->actingAs($this->admin)->get(route('admin.eventos.show', $event))->assertOk()->assertSee($stream->stream_key);
    }

    public function test_nginx_never_serves_raw_hls_files_named_after_the_key(): void
    {
        $conf = file_get_contents(base_path('docker/nginx.conf'));
        $this->assertMatchesRegularExpression('#location \^~ /hls/ \{\s*return 404;\s*\}#', $conf);
        $this->assertMatchesRegularExpression('#location \^~ /_protected_hls/ \{\s*internal;#', $conf);
    }

    public function test_internal_events_require_a_session_everywhere(): void
    {
        $event = $this->liveEvent('interno');
        $stream = $event->transmision;

        $this->get(route('eventos.show', $event))->assertRedirect(route('login'));
        $this->get($stream->playbackUrl())->assertNotFound();
        $this->get('/directo/'.$stream->playback_id.'/7.ts')->assertNotFound();
        $this->getJson(route('eventos.chat.index', $event))->assertNotFound();
        $this->postJson(route('eventos.viewers', $event), ['viewer' => (string) Str::uuid()])->assertNotFound();
        $this->get(route('eventos.index'))->assertOk()->assertDontSee($event->titulo);
        // Internal content never airs on the public 24/7 channel.
        $this->getJson(route('channel.state'))->assertJsonPath('mode', 'idle');

        $user = User::factory()->create(['role' => 'usuario']);
        $this->actingAs($user)->get(route('eventos.show', $event))->assertOk();
        $this->get($stream->playbackUrl())->assertOk();
        $this->getJson(route('eventos.chat.index', $event))->assertOk();
        $this->get(route('eventos.index'))->assertOk()->assertSee($event->titulo);
    }

    public function test_restricted_events_are_only_for_staff_and_are_not_announced(): void
    {
        $event = $this->liveEvent('restringido');
        $stream = $event->transmision;
        $user = User::factory()->create(['role' => 'usuario']);
        $operator = User::factory()->create(['role' => 'operador', 'operator_permissions' => ['moderate_chat']]);

        $this->actingAs($user)->get(route('eventos.show', $event))->assertNotFound();
        $this->get($stream->playbackUrl())->assertNotFound();
        $this->get(route('eventos.index'))->assertOk()->assertDontSee($event->titulo);
        $this->actingAs($operator)->get(route('eventos.show', $event))->assertOk();
        $this->get($stream->playbackUrl())->assertOk();

        app(\App\Services\AudienceAnnouncements::class)->publish($event, 'live');
        $this->assertDatabaseMissing('audience_announcements', ['evento_id' => $event->id]);
    }

    public function test_visibility_is_editable_validated_and_audited(): void
    {
        $event = $this->liveEvent();
        $this->actingAs($this->admin)->get(route('admin.eventos.edit', $event))->assertOk()
            ->assertSee('visibilities', false)->assertSee('Restringido', false);
        $payload = ['titulo' => $event->titulo, 'tipo_evento' => 'Conferencia', 'fecha_inicio' => now()->format('Y-m-d\TH:i'),
            'categoria_id' => $event->categoria_id];
        $this->put(route('admin.eventos.update', $event), $payload + ['visibilidad' => 'secreto'])->assertSessionHasErrors('visibilidad');
        $this->put(route('admin.eventos.update', $event), $payload + ['visibilidad' => 'interno'])->assertSessionHasNoErrors();
        $this->assertSame('interno', $event->fresh()->visibilidad);

        $log = \App\Models\AuditLog::where('action', 'evento.actualizado')->latest('id')->firstOrFail();
        $this->assertSame($this->admin->id, $log->user_id);
        $this->assertSame($event->id, $log->subject_id);
        $this->assertSame(['antes' => 'publico', 'despues' => 'interno'], $log->details['cambios']['visibilidad']);
        $this->assertNotNull($log->created_at);
        // A partial update without the field keeps the current classification.
        $this->put(route('admin.eventos.update', $event), $payload)->assertSessionHasNoErrors();
        $this->assertSame('interno', $event->fresh()->visibilidad);
    }

    public function test_only_administrators_review_recordings_and_read_the_audit_log(): void
    {
        $event = $this->liveEvent();
        $operator = User::factory()->create(['role' => 'operador', 'operator_permissions' => User::operatorActions()]);
        $this->actingAs($operator)->post(route('admin.recording.approve', $event))->assertForbidden();
        $this->post(route('admin.recording.withdraw', $event))->assertForbidden();
        $this->get(route('admin.audit.index'))->assertForbidden();
        // A recording that is not ready cannot be approved.
        $this->actingAs($this->admin)->post(route('admin.recording.approve', $event))->assertStatus(409);

        $this->post(route('streaming.generar.clave', Evento::create(['titulo' => 'Otro', 'slug' => 'otro', 'tipo_evento' => 'Conferencia',
            'categoria_id' => $event->categoria_id, 'organizador_id' => $this->admin->id, 'fecha_inicio' => now(), 'estado' => 'programado'])));
        $this->get(route('admin.audit.index'))->assertOk()->assertSee('transmision.clave_generada', false)
            ->assertSee($this->admin->email, false);
    }

    public function test_user_permission_changes_are_audited_with_before_and_after(): void
    {
        $operator = User::factory()->create(['role' => 'operador', 'operator_permissions' => User::operatorActions()]);
        $this->actingAs($this->admin)->put(route('admin.usuarios.update', $operator), [
            'name' => $operator->name, 'email' => $operator->email, 'role' => 'operador',
            'permissions' => ['stream'], 'permissions_present' => '1',
        ])->assertSessionHasNoErrors();

        $log = \App\Models\AuditLog::where('action', 'usuario.actualizado')->firstOrFail();
        $this->assertSame(User::operatorActions(), $log->details['antes']['permisos']);
        $this->assertSame(['stream'], $log->details['despues']['permisos']);
        $this->actingAs($operator->fresh())->get(route('admin.eventos.index'))->assertForbidden();
        $this->get(route('streaming.control'))->assertOk();
    }

    public function test_new_streams_get_a_playback_id_and_store_only_the_public_url(): void
    {
        $this->actingAs($this->admin)->post(route('admin.eventos.store'), [
            'titulo' => 'Nuevo directo', 'tipo_evento' => 'Conferencias', 'fecha_inicio' => now()->addDay()->format('Y-m-d\TH:i'),
            'categoria_id' => Categoria::firstOrCreate(['slug' => 'general'], ['nombre' => 'General'])->id,
        ])->assertSessionHasNoErrors();
        $stream = Transmision::latest('id')->firstOrFail();
        $this->assertSame($stream->playbackUrl(), $stream->hls_url);
        $this->assertStringNotContainsString($stream->stream_key, $stream->hls_url);
        $this->assertSame('publico', $stream->evento->visibilidad);
        $this->assertDatabaseHas('audit_logs', ['action' => 'evento.creado', 'subject_id' => $stream->evento_id, 'user_id' => $this->admin->id]);
    }
}
