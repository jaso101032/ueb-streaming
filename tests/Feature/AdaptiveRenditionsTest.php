<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Evento;
use App\Models\User;
use App\Services\AdaptiveRenditions;
use App\Services\ScheduledVideoProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/** Calidades adaptativas (HLS) de grabaciones y videos programados, con FFmpeg real. */
class AdaptiveRenditionsTest extends TestCase
{
    use RefreshDatabase;

    private string $root;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        if (! is_executable(config('streaming.ffmpeg')) || ! is_executable(config('streaming.ffprobe'))) {
            $this->markTestSkipped('FFmpeg no está instalado.');
        }
        Storage::fake('local');
        $this->root = sys_get_temp_dir().'/uebtv-renditions-'.Str::uuid();
        mkdir($this->root.'/vod', 0700, true);
        config(['streaming.recordings_root' => $this->root, 'streaming.renditions.enabled' => true,
            'streaming.renditions.preset' => 'ultrafast', 'streaming.renditions.threads' => 2]);
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->root.'/vod/*') ?: []);
        @rmdir($this->root.'/vod');
        @rmdir($this->root);
        parent::tearDown();
    }

    private function video(string $path, string $size = '854x480', bool $audio = true, int $seconds = 5): void
    {
        $command = [config('streaming.ffmpeg'), '-nostdin', '-loglevel', 'error', '-y', '-f', 'lavfi', '-i', "testsrc2=s=$size:r=15:d=$seconds"];
        if ($audio) array_push($command, '-f', 'lavfi', '-i', "sine=frequency=440:duration=$seconds", '-c:a', 'aac');
        array_push($command, '-c:v', 'libx264', '-preset', 'ultrafast', '-pix_fmt', 'yuv420p', '-shortest', $path);
        (new Process($command))->mustRun();
    }

    private function recordedEvent(string $publication = 'aprobada', string $size = '854x480'): Evento
    {
        $category = Categoria::firstOrCreate(['slug' => 'general'], ['nombre' => 'General']);
        $event = Evento::create(['titulo' => 'Clase', 'slug' => 'clase-'.Str::uuid(), 'tipo_evento' => 'Conferencia',
            'categoria_id' => $category->id, 'organizador_id' => $this->admin->id, 'fecha_inicio' => now(), 'estado' => 'finalizado']);
        $file = Str::uuid().'.mp4';
        $this->video($this->root.'/vod/'.$file, $size);
        $event->transmision()->create(['stream_key' => 'ueb_'.Str::random(12), 'rtmp_server' => 'r',
            'recording_status' => 'ready', 'recording_file' => $file, 'publicacion' => $publication]);

        return $event;
    }

    private function work(): void
    {
        $this->artisan('media:renditions')->assertSuccessful();
    }

    public function test_the_ladder_never_upscales_and_keeps_tiny_videos_playable(): void
    {
        $service = app(AdaptiveRenditions::class);
        $names = fn (int $height) => array_column($service->ladderFor($height), 'name');
        $this->assertSame(['1080p', '720p', '480p', '360p'], $names(1440));
        $this->assertSame(['720p', '480p', '360p'], $names(720));
        $this->assertSame(['360p'], $names(400));
        $this->assertSame(['240p'], $names(241));
    }

    public function test_recordings_get_adaptive_qualities_served_with_the_recording_permissions(): void
    {
        $event = $this->recordedEvent();
        $this->work();

        $stream = $event->transmision->fresh();
        $this->assertSame('ready', $stream->renditions_status);
        $this->assertSame(['480p', '360p'], array_column($stream->renditions_levels, 'name'));
        $this->assertSame([854, 480], [$stream->renditions_levels[0]['width'], $stream->renditions_levels[0]['height']]);
        $this->assertSame(1, $stream->renditions_attempts);

        // The watch page gives the player the adaptive playlist and keeps the MP4 as a fallback.
        $this->get(route('eventos.show', $event))->assertOk()
            ->assertSee('/eventos/'.$event->id.'/calidades/master.m3u8', false)
            ->assertSee(route('eventos.recording', $event), false);

        $master = $this->get(route('eventos.renditions', [$event, 'master.m3u8']))->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.apple.mpegurl');
        $this->assertStringContainsString("480p/index.m3u8", $master->streamedContent());
        $playlist = $this->get(route('eventos.renditions', [$event, '480p/index.m3u8']))->assertOk()->streamedContent();
        $this->assertStringContainsString('#EXT-X-ENDLIST', $playlist);
        $this->assertStringContainsString('seg_00000.ts', $playlist);
        $this->get(route('eventos.renditions', [$event, '480p/seg_00000.ts']))->assertOk()->assertHeader('Content-Type', 'video/mp2t');

        // Unknown files and path tricks never reach the disk.
        $this->get('/eventos/'.$event->id.'/calidades/..%2F..%2F.env')->assertNotFound();
        $this->get('/eventos/'.$event->id.'/calidades/480p/seg_99999.ts')->assertNotFound();

        // Same rules as the recording: withdrawn or private events are not served.
        $stream->update(['publicacion' => 'retirada']);
        $this->get(route('eventos.renditions', [$event, 'master.m3u8']))->assertNotFound();
        $this->get(route('eventos.show', $event))->assertDontSee('calidades/master.m3u8', false);
        $this->actingAs($this->admin)->get(route('eventos.renditions', [$event, 'master.m3u8']))->assertOk();
        $this->get(route('admin.eventos.show', $event))->assertOk()->assertSee('"levels":["480p","360p"]');
    }

    public function test_scheduled_programs_get_qualities_only_while_they_can_be_watched(): void
    {
        $category = Categoria::firstOrCreate(['slug' => 'general'], ['nombre' => 'General']);
        $event = Evento::create(['titulo' => 'Programa', 'slug' => 'programa', 'tipo_evento' => 'Emisión pregrabada',
            'categoria_id' => $category->id, 'organizador_id' => $this->admin->id, 'fecha_inicio' => now()->addDay(), 'estado' => 'programado']);
        Storage::disk('local')->makeDirectory('scheduled-videos');
        $this->video(Storage::disk('local')->path('scheduled-videos/programa.mp4'), '640x360', false);
        $scheduled = $event->scheduledVideo()->create(['path' => 'scheduled-videos/programa.mp4', 'duration' => 5,
            'processing_status' => 'ready', 'ready_at' => now()]);

        $this->work();
        $this->assertSame(['360p'], array_column($scheduled->fresh()->renditions_levels, 'name'), 'Videos without audio work too.');

        $this->get(route('eventos.renditions', [$event, 'master.m3u8']))->assertNotFound(); // not on air yet
        $event->update(['fecha_inicio' => now()->subSecond()]);
        $this->get(route('eventos.renditions', [$event, 'master.m3u8']))->assertOk();
        $this->getJson(route('channel.state'))->assertJsonPath('event.scheduled.adaptiveUrl', '/eventos/'.$event->id.'/calidades/master.m3u8');
    }

    public function test_broken_videos_are_retried_later_and_the_feature_can_be_disabled(): void
    {
        $event = $this->recordedEvent();
        file_put_contents($this->root.'/vod/'.$event->transmision->recording_file, 'no es un video');
        $this->work();
        $stream = $event->transmision->fresh();
        $this->assertSame('failed', $stream->renditions_status);
        $this->assertNotNull($stream->renditions_error);
        $this->assertSame([], Storage::disk('local')->allFiles('renditions'), 'Partial output is removed.');
        $this->get(route('eventos.show', $event))->assertOk()->assertDontSee('calidades/master.m3u8', false);

        $this->work();
        $this->assertSame(1, $event->transmision->fresh()->renditions_attempts, 'Waits ten minutes before retrying.');
        $this->travel(11)->minutes();
        $this->work();
        $this->assertSame(2, $event->transmision->fresh()->renditions_attempts);

        config(['streaming.renditions.enabled' => false]);
        $other = $this->recordedEvent();
        $this->work();
        $this->assertNull($other->transmision->fresh()->renditions_status);
    }

    public function test_qualities_survive_the_trash_and_are_removed_on_permanent_deletion(): void
    {
        $event = $this->recordedEvent();
        $this->work();
        $directory = $event->transmision->fresh()->renditions_path;
        Storage::disk('local')->assertExists($directory.'/master.m3u8');

        $event->delete();
        Storage::disk('local')->assertExists($directory.'/master.m3u8');
        $event->forceDelete();
        Storage::disk('local')->assertMissing($directory);
    }

    public function test_the_optimized_mp4_respects_the_configured_maximum_height(): void
    {
        $this->assertStringContainsString("min(1920,iw)':h='min(1080,ih)", ScheduledVideoProcessor::scaleFilter());
        config(['streaming.max_height' => 720]);
        $this->assertStringContainsString("min(1280,iw)':h='min(720,ih)", ScheduledVideoProcessor::scaleFilter());

        $source = $this->root.'/vod/fuente.mp4';
        $output = $this->root.'/vod/salida.mp4';
        $this->video($source, '1920x1080', true, 2);
        app(ScheduledVideoProcessor::class)->transcode($source, $output, 2);
        $probe = new Process([config('streaming.ffprobe'), '-v', 'error', '-select_streams', 'v:0',
            '-show_entries', 'stream=width,height', '-of', 'csv=p=0', $output]);
        $this->assertSame('1280,720', trim($probe->mustRun()->getOutput()));
    }
}
