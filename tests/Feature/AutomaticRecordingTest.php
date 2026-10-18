<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Evento;
use App\Models\User;
use App\Services\RecordingProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class AutomaticRecordingTest extends TestCase
{
    use RefreshDatabase;
    private string $root;
    private Evento $event;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = sys_get_temp_dir().'/uebtv-recording-test-'.Str::uuid();
        File::makeDirectory($this->root.'/raw', 0770, true);
        File::makeDirectory($this->root.'/vod', 0770, true);
        config(['streaming.recordings_root' => $this->root, 'streaming.reconnect_grace' => 90,
            'streaming.callback_token' => str_repeat('a', 64)]);
        $admin = User::factory()->create(['role' => 'admin']);
        $category = Categoria::create(['nombre' => 'General', 'slug' => 'general']);
        $this->event = Evento::create(['titulo' => 'Grabación', 'slug' => 'grabacion', 'tipo_evento' => 'Conferencias',
            'categoria_id' => $category->id, 'organizador_id' => $admin->id,
            'fecha_inicio' => now(), 'estado' => 'programado', 'video_url' => 'https://example.com/manual.mp4']);
        $this->event->transmision()->create(['stream_key' => 'ueb_test']);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->root);
        parent::tearDown();
    }

    private function publish(string $client = '10', string $call = 'publish')
    {
        return $this->post('/streaming/hooks/authorize?token='.str_repeat('a', 64),
            ['app' => 'live', 'name' => 'ueb_test', 'call' => $call, 'clientid' => $client]);
    }

    private function disconnect(string $client = '10')
    {
        return $this->post('/streaming/hooks/disconnected?token='.str_repeat('a', 64),
            ['app' => 'live', 'name' => 'ueb_test', 'clientid' => $client]);
    }

    public function test_reconnection_continues_the_same_recording_and_ignores_old_disconnect(): void
    {
        $this->publish()->assertNoContent();
        $this->assertSame('en_vivo', $this->event->fresh()->estado);
        $started = $this->event->transmision()->first()->iniciado_en;
        $this->disconnect()->assertNoContent();
        $this->travel(30)->seconds();
        $this->artisan('streaming:prepare-recordings')->assertSuccessful();
        $this->assertSame('waiting', $this->event->transmision()->first()->recording_status);
        $this->publish('11')->assertNoContent();
        $this->disconnect('10')->assertNoContent();
        $stream = $this->event->transmision()->first();
        $this->assertTrue($stream->activo);
        $this->assertSame('11', $stream->publisher_id);
        $this->assertEquals($started, $stream->iniciado_en);
        $this->publish('10', 'update_publish')->assertForbidden();
        $this->publish('11', 'update_publish')->assertNoContent();
    }

    public function test_real_video_is_converted_published_and_supports_range_requests(): void
    {
        $this->event->transmision()->update(['recording_fps_limit' => 30]);
        $this->publish()->assertNoContent();
        $fixture = new Process(['/usr/bin/ffmpeg', '-nostdin', '-v', 'error', '-f', 'lavfi', '-i', 'color=c=blue:s=160x90:r=60',
            '-f', 'lavfi', '-i', 'sine=frequency=440:sample_rate=44100', '-t', '1',
            '-c:v', 'libx264', '-threads', '1', '-c:a', 'aac', '-f', 'flv', $this->root.'/raw/ueb_test.flv']);
        $fixture->mustRun();
        $this->disconnect()->assertNoContent();
        $this->travel(91)->seconds();
        $this->artisan('streaming:prepare-recordings')->assertSuccessful();
        $stream = $this->event->transmision()->first();
        $this->assertSame('ready', $stream->recording_status, $stream->recording_error ?? '');
        $this->assertSame('MP4', $stream->recording_metadata['format']);
        $this->assertSame('h264', $stream->recording_metadata['videoCodec']);
        $this->assertSame('aac', $stream->recording_metadata['audioCodec']);
        $this->assertSame(160, $stream->recording_metadata['width']);
        $this->assertEqualsWithDelta(30, $stream->recording_metadata['fps'], 0.1);
        $this->assertSame(filesize($this->root.'/raw/ueb_test.flv'), $stream->recording_metadata['rawBytes']);
        $this->assertSame(filesize($stream->recordingPath()), $stream->recording_metadata['mp4Bytes']);
        $this->assertFileExists($stream->recordingPath());
        $this->assertFileExists($this->root.'/raw/ueb_test.flv');
        $this->assertSame('https://example.com/manual.mp4', $this->event->fresh()->video_url);
        $this->assertSame('finalizado', $this->event->fresh()->estado);
        $this->actingAs(User::where('role', 'admin')->first());
        $this->get(route('admin.eventos.show', $this->event))->assertOk()->assertSee('recordingMetadata');
        auth()->logout();
        $this->get(route('eventos.show', $this->event))->assertOk()->assertSee(route('eventos.recording', $this->event));
        $this->get(route('eventos.recording', $this->event), ['Range' => 'bytes=0-31'])->assertStatus(206)
            ->assertHeader('Content-Type', 'video/mp4');
        config(['streaming.x_accel_media' => true]);
        $this->get(route('eventos.recording', $this->event))->assertOk()
            ->assertHeader('X-Accel-Redirect', '/_protected_media/recordings/'.$stream->recording_file);
        $this->publish()->assertForbidden();
        $this->artisan('streaming:prepare-recordings')->assertSuccessful();
        $this->assertSame($stream->recording_file, $stream->fresh()->recording_file);
    }

    public function test_failure_keeps_manual_fallback_and_can_be_retried_by_operator(): void
    {
        $this->publish()->assertNoContent();
        file_put_contents($this->root.'/raw/ueb_test.flv', 'not a real recording');
        $this->disconnect();
        $this->travel(91)->seconds();
        app(RecordingProcessor::class)->process($this->event->transmision()->first()->id);
        $this->assertSame('failed', $this->event->transmision()->first()->recording_status);
        $this->assertFileExists($this->root.'/raw/ueb_test.flv');
        $this->get(route('eventos.show', $this->event))->assertSee('https://example.com/manual.mp4');
        $this->get(route('eventos.recording', $this->event))->assertNotFound();
        $this->actingAs(User::factory()->create(['role' => 'usuario']));
        $this->post(route('streaming.recording.retry', $this->event))->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->post(route('streaming.recording.retry', $this->event))->assertSessionHas('success');
        $this->assertSame('waiting', $this->event->transmision()->first()->recording_status);
    }

    public function test_missing_disconnect_callback_is_recovered_after_grace(): void
    {
        $this->publish()->assertNoContent();
        $this->travel(91)->seconds();
        $this->artisan('streaming:prepare-recordings')->assertSuccessful();
        $this->assertSame('finalizado', $this->event->fresh()->estado);
        $this->assertSame('failed', $this->event->transmision()->first()->recording_status);
    }

    public function test_current_heartbeat_prevents_processing_and_key_rotation(): void
    {
        $this->publish()->assertNoContent();
        $this->travel(85)->seconds();
        $this->publish('10', 'update_publish')->assertNoContent();
        $this->travel(10)->seconds();
        $this->artisan('streaming:prepare-recordings')->assertSuccessful();
        $this->assertSame('recording', $this->event->transmision()->first()->recording_status);
        $this->actingAs(User::where('role', 'admin')->first());
        $this->post(route('streaming.generar.clave', $this->event))->assertStatus(422);
    }

    public function test_unpublished_and_cancelled_files_are_not_served(): void
    {
        $stream = $this->event->transmision()->first();
        $filename = Str::uuid().'.mp4';
        file_put_contents($this->root.'/vod/'.$filename, 'private');
        $stream->update(['recording_status' => 'processing', 'recording_file' => $filename]);
        $this->get(route('eventos.recording', $this->event))->assertNotFound();
        $stream->update(['recording_status' => 'ready']);
        $this->event->update(['estado' => 'cancelado']);
        $this->get(route('eventos.recording', $this->event))->assertNotFound();
    }

    public function test_missing_ready_file_falls_back_to_manual_link(): void
    {
        $this->event->update(['estado' => 'finalizado']);
        $this->event->transmision()->update(['recording_status' => 'ready', 'recording_file' => Str::uuid().'.mp4']);
        $this->get(route('eventos.show', $this->event))->assertOk()->assertSee('https://example.com/manual.mp4');
    }
}
