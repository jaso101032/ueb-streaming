<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Evento;
use App\Models\InstitutionalAd;
use App\Models\User;
use App\Services\AutomaticSubtitles;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Subtítulos automáticos (Whisper) para videos almacenados; nunca para el directo. */
class AutomaticSubtitlesTest extends TestCase
{
    use RefreshDatabase;

    private string $root;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->root = sys_get_temp_dir().'/uebtv-auto-subtitles-'.Str::uuid();
        mkdir($this->root.'/vod', 0700, true);
        config(['streaming.recordings_root' => $this->root,
            'streaming.auto_subtitles.enabled' => true,
            'streaming.auto_subtitles.python' => PHP_BINARY,
            'streaming.auto_subtitles.script' => base_path('tests/Fixtures/fake-subtitle-engine.php')]);
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->root.'/vod/*') ?: []);
        @rmdir($this->root.'/vod');
        @rmdir($this->root);
        parent::tearDown();
    }

    private function recordedEvent(string $audio = 'speech', array $stream = []): Evento
    {
        $category = Categoria::firstOrCreate(['slug' => 'general'], ['nombre' => 'General']);
        $event = Evento::create(['titulo' => 'Clase grabada', 'slug' => 'clase-'.Str::uuid(), 'tipo_evento' => 'Conferencia',
            'categoria_id' => $category->id, 'organizador_id' => $this->admin->id, 'fecha_inicio' => now(), 'estado' => 'finalizado']);
        $file = Str::uuid().'.mp4';
        file_put_contents($this->root.'/vod/'.$file, $audio);
        $event->transmision()->create($stream + ['stream_key' => 'ueb_'.Str::random(12), 'rtmp_server' => 'r',
            'recording_status' => 'ready', 'recording_file' => $file, 'publicacion' => 'aprobada']);

        return $event;
    }

    private function work(): void
    {
        $this->artisan('subtitles:generate')->assertSuccessful();
    }

    public function test_finished_recordings_get_automatic_subtitles_that_play_with_the_cc_button(): void
    {
        $event = $this->recordedEvent();
        $this->work();

        $stream = $event->transmision->fresh();
        $this->assertSame('auto', $stream->subtitles_source);
        $this->assertSame('ready', $stream->subtitles_status);
        $this->assertSame(100, $stream->subtitles_progress);
        $this->assertSame(1, $stream->subtitles_attempts);
        $vtt = Storage::disk('local')->get($stream->subtitles_path);
        $this->assertStringContainsString("00:00:01.000 --> 00:00:03.000\nBienvenidos a <i>UEBTV</i>", $vtt);
        $this->assertStringNotContainsString('<script>', $vtt);
        $this->assertDatabaseHas('audit_logs', ['action' => 'subtitulos.generados_automaticamente', 'subject_id' => $event->id]);

        // The public player receives the track (the CC button appears when subtitlesUrl is set).
        $this->get(route('eventos.show', $event))->assertOk()->assertSee('/eventos/'.$event->id.'/subtitulos.vtt', false);
        $this->assertSame($vtt, $this->get(route('eventos.subtitles', $event))->assertOk()->getContent());

        // Administrators see that they were generated automatically.
        $this->actingAs($this->admin)->get(route('admin.eventos.show', $event))->assertOk()
            ->assertSee('"source":"auto"')->assertSee('"status":"ready"');

        // Running the worker again does nothing.
        $path = $stream->subtitles_path;
        $this->work();
        $this->assertSame($path, $event->transmision->fresh()->subtitles_path);
    }

    public function test_scheduled_programs_and_advertising_are_transcribed_too(): void
    {
        $category = Categoria::firstOrCreate(['slug' => 'general'], ['nombre' => 'General']);
        $event = Evento::create(['titulo' => 'Programa', 'slug' => 'programa', 'tipo_evento' => 'Emisión pregrabada',
            'categoria_id' => $category->id, 'organizador_id' => $this->admin->id, 'fecha_inicio' => now()->subSecond(), 'estado' => 'programado']);
        Storage::disk('local')->put('scheduled-videos/programa.mp4', 'speech');
        $scheduled = $event->scheduledVideo()->create(['path' => 'scheduled-videos/programa.mp4', 'duration' => 600, 'processing_status' => 'ready', 'ready_at' => now()]);
        Storage::disk('local')->put('institutional-ads/aviso.mp4', 'speech');
        $ad = InstitutionalAd::create(['title' => 'Aviso', 'path' => 'institutional-ads/aviso.mp4', 'duration' => 30,
            'processing_status' => 'ready', 'is_active' => true, 'ready_at' => now()]);
        Storage::disk('local')->put('institutional-ads/pendiente.mp4', 'speech');
        $converting = InstitutionalAd::create(['title' => 'En conversión', 'path' => 'institutional-ads/pendiente.mp4', 'duration' => 30,
            'processing_status' => 'processing', 'is_active' => true]);

        $this->work();

        $this->assertSame('auto', $scheduled->fresh()->subtitles_source);
        $this->assertSame('auto', $ad->fresh()->subtitles_source);
        $this->assertNull($converting->fresh()->subtitles_path, 'Videos still converting wait until they are ready.');
        $this->get(route('eventos.subtitles', $event))->assertOk();
        $this->get(route('channel.ad.subtitles', $ad))->assertOk();
        $this->actingAs($this->admin)->get(route('admin.scheduled.index'))->assertOk()->assertSee('"source":"auto"');
    }

    public function test_live_streams_and_unfinished_recordings_are_never_transcribed(): void
    {
        $live = $this->recordedEvent('speech', ['recording_status' => 'recording']);
        $converting = $this->recordedEvent('speech', ['recording_status' => 'processing']);
        $this->work();

        $this->assertNull($live->transmision->fresh()->subtitles_path);
        $this->assertNull($live->transmision->fresh()->subtitles_status);
        $this->assertNull($converting->transmision->fresh()->subtitles_path);
    }

    public function test_staff_subtitles_are_never_overwritten_and_removed_ones_are_not_regenerated(): void
    {
        $manual = $this->recordedEvent();
        $this->actingAs($this->admin)->post(route('admin.subtitles.store', $manual), ['subtitles' => UploadedFile::fake()
            ->createWithContent('a.vtt', "WEBVTT\n\n00:00:01.000 --> 00:00:02.000\nTexto revisado\n")])->assertSessionHasNoErrors();
        $this->assertSame('manual', $manual->transmision->fresh()->subtitles_source);

        $removed = $this->recordedEvent();
        $this->work();
        $this->assertSame('auto', $removed->transmision->fresh()->subtitles_source);
        $this->delete(route('admin.subtitles.destroy', $removed))->assertSessionHas('success');
        $this->assertSame('skipped', $removed->transmision->fresh()->subtitles_status);

        $this->work();
        $this->assertStringContainsString('Texto revisado', Storage::disk('local')->get($manual->transmision->fresh()->subtitles_path));
        $this->assertNull($removed->transmision->fresh()->subtitles_path);

        // An administrator can ask for them again.
        $this->post(route('admin.subtitles.generate', $removed))->assertSessionHas('success');
        $this->assertSame('pending', $removed->transmision->fresh()->subtitles_status);
        $this->post(route('admin.subtitles.generate', $removed))->assertStatus(409);
        $this->work();
        $this->assertSame('auto', $removed->transmision->fresh()->subtitles_source);
        $this->post(route('admin.subtitles.generate', $removed))->assertStatus(409);
        $this->assertDatabaseHas('audit_logs', ['action' => 'subtitulos.generacion_solicitada', 'subject_id' => $removed->id]);
    }

    public function test_a_manual_upload_during_transcription_wins(): void
    {
        $event = $this->recordedEvent();
        $stream = $event->transmision;
        // Simulate the staff uploading subtitles while Whisper is still working.
        $service = new class extends AutomaticSubtitles
        {
            public function mediaPath(Model $media): ?string
            {
                $media->newQuery()->whereKey($media->getKey())->update(['subtitles_path' => 'subtitles/manual.vtt',
                    'subtitles_source' => 'manual', 'subtitles_status' => 'ready']);

                return parent::mediaPath($media);
            }
        };
        $this->assertSame('superseded', $service->process($stream));
        $fresh = $stream->fresh();
        $this->assertSame('subtitles/manual.vtt', $fresh->subtitles_path);
        $this->assertSame('manual', $fresh->subtitles_source);
        $this->assertSame([], Storage::disk('local')->allFiles('subtitles'), 'The discarded automatic file is deleted.');
    }

    public function test_videos_without_speech_are_marked_and_not_retried(): void
    {
        $event = $this->recordedEvent('silence');
        $this->work();
        $stream = $event->transmision->fresh();
        $this->assertSame('no_speech', $stream->subtitles_status);
        $this->assertNull($stream->subtitles_path);

        $this->travel(1)->day();
        $this->work();
        $this->assertSame(1, $event->transmision->fresh()->subtitles_attempts);
        $this->actingAs($this->admin)->get(route('admin.eventos.show', $event))->assertOk()->assertSee('"status":"no_speech"');
    }

    public function test_failures_are_retried_later_up_to_three_times(): void
    {
        $event = $this->recordedEvent('crash');
        $this->work();
        $stream = $event->transmision->fresh();
        $this->assertSame('failed', $stream->subtitles_status);
        $this->assertSame(1, $stream->subtitles_attempts);
        $this->assertNotNull($stream->subtitles_error);

        $this->work(); // too soon: waits ten minutes between attempts
        $this->assertSame(1, $event->transmision->fresh()->subtitles_attempts);
        foreach ([2, 3] as $attempt) {
            $this->travel(11)->minutes();
            $this->work();
            $this->assertSame($attempt, $event->transmision->fresh()->subtitles_attempts);
        }
        $this->travel(11)->minutes();
        $this->work();
        $this->assertSame(3, $event->transmision->fresh()->subtitles_attempts, 'Gives up after three attempts.');

        // The administrator can retry by hand once the problem is fixed.
        $file = $this->root.'/vod/'.$event->transmision->recording_file;
        file_put_contents($file, 'speech');
        $this->actingAs($this->admin)->get(route('admin.eventos.show', $event))->assertOk()
            ->assertSee(route('admin.subtitles.generate', $event), false);
        $this->post(route('admin.subtitles.generate', $event))->assertSessionHas('success');
        $this->work();
        $this->assertSame('auto', $event->transmision->fresh()->subtitles_source);
    }

    public function test_missing_engine_or_video_file_is_reported_clearly(): void
    {
        $event = $this->recordedEvent('missing-engine');
        $this->work();
        $this->assertStringContainsString('faster-whisper', $event->transmision->fresh()->subtitles_error);

        Storage::disk('local')->put('institutional-ads/ok.mp4', 'speech');
        $ad = InstitutionalAd::create(['title' => 'Sin archivo', 'path' => 'institutional-ads/borrado.mp4', 'duration' => 30,
            'processing_status' => 'ready', 'is_active' => true, 'ready_at' => now()]);
        $this->work();
        $ad->refresh();
        $this->assertSame('failed', $ad->subtitles_status);
        $this->assertSame('No se encontró el archivo de video.', $ad->subtitles_error);
        $this->assertSame(3, $ad->subtitles_attempts);
    }

    public function test_only_administrators_can_request_generation_and_the_feature_can_be_disabled(): void
    {
        $event = $this->recordedEvent('speech', ['subtitles_status' => 'skipped']);
        $operator = User::factory()->create(['role' => 'operador', 'operator_permissions' => User::operatorActions()]);
        $this->actingAs($operator)->post(route('admin.subtitles.generate', $event))->assertForbidden();
        auth()->logout();
        $this->post(route('admin.subtitles.generate', $event))->assertRedirect();
        $this->assertSame('skipped', $event->transmision->fresh()->subtitles_status);

        config(['streaming.auto_subtitles.enabled' => false]);
        $other = $this->recordedEvent();
        $this->work();
        $this->assertNull($other->transmision->fresh()->subtitles_path);
        $this->actingAs($this->admin)->post(route('admin.subtitles.generate', $event))->assertStatus(409);
    }
}
