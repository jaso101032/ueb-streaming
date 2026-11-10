<?php
namespace Tests\Feature;
use App\Models\{Evento, User, Categoria};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
class ScheduledVideoTest extends TestCase {
    use RefreshDatabase;
    private function poster(): UploadedFile {
        $chunk = fn (string $type, string $data) => pack('N', strlen($data)).$type.$data.pack('N', crc32($type.$data));
        $png = "\x89PNG\r\n\x1a\n"
            .$chunk('IHDR', pack('NNCCCCC', 1, 1, 8, 2, 0, 0, 0))
            .$chunk('IDAT', gzcompress("\0\xff\0\0"))
            .$chunk('IEND', '');

        return UploadedFile::fake()->createWithContent('portada.png', $png);
    }
    private function event() {
        Storage::fake('local');
        $user = User::factory()->create(['role'=>'admin']);
        $category = Categoria::create(['nombre'=>'General','slug'=>'general']);
        $event = Evento::create(['titulo'=>'Estreno', 'slug'=>'estreno','organizador_id'=>$user->id,'categoria_id'=>$category->id,'tipo_evento'=>'Emisión pregrabada','estado'=>'programado','fecha_inicio'=>now()->addMinutes(10),'fecha_fin'=>now()->addMinutes(12)]);
        $event->scheduledVideo()->create(['path'=>'scheduled-videos/test.mp4','duration'=>120]);
        Storage::disk('local')->put('scheduled-videos/test.mp4','test');
        return $event;
    }
    public function test_server_clock_controls_access_and_position(): void {
        $event=$this->event();
        $this->get(route('scheduled.state',$event))->assertOk()->assertJsonPath('state','upcoming');
        $this->get(route('scheduled.media',$event))->assertNotFound();
        $this->travel(11)->minutes();
        $this->get(route('scheduled.state',$event))->assertOk()->assertJsonPath('state','playing')->assertJsonPath('position',60);
        $this->get(route('scheduled.media',$event))->assertOk();
        $this->get(route('eventos.show',$event))->assertOk()->assertSee(route('scheduled.state', $event), false);
        $this->travel(2)->minutes();
        $this->get(route('scheduled.state',$event))->assertJsonPath('state','ended');
        $this->get(route('eventos.index',['filtro'=>'anteriores']))->assertOk()->assertSee('Estreno');
        $event->delete();
        $this->get(route('scheduled.media',$event))->assertNotFound();
    }
    public function test_coverage_reports_the_next_gap_and_active_ad_fallback(): void {
        $event = $this->event();
        $event->update(['fecha_inicio' => now()->subSeconds(30)]);
        $summary = app(\App\Services\ChannelSchedule::class)->summary(null, true);
        $this->assertSame(now()->addSeconds(90)->timestamp, \Carbon\Carbon::parse($summary['firstGapAt'])->timestamp);
        $this->assertFalse($summary['hasAdFallback']);

        Storage::disk('local')->put('institutional-ads/active.mp4', 'ad');
        \App\Models\InstitutionalAd::create([
            'title' => 'Mensaje institucional', 'path' => 'institutional-ads/active.mp4',
            'duration' => 30, 'processing_status' => 'ready', 'is_active' => true,
        ]);
        $summary = app(\App\Services\ChannelSchedule::class)->summary(null, true);
        $this->assertTrue($summary['hasAdFallback']);
    }
    public function test_admin_only_upload_and_validation(): void {
        $this->event();
        foreach(['operador','estudiante'] as $role) {
            $this->actingAs(User::factory()->create(['role'=>$role]));
            $this->get(route('admin.scheduled.index'))->assertForbidden();
            $this->post(route('admin.scheduled.store'),[])->assertForbidden();
        }
        $this->actingAs(User::where('role','admin')->first());
        $this->get(route('admin.scheduled.index'))->assertOk();
        $this->post(route('admin.scheduled.store'),[])->assertSessionHasErrors(['titulo','video','fecha_inicio']);
    }
    public function test_production_media_handoff_keeps_the_scheduled_video_private(): void {
        $event = $this->event();
        $name = \Illuminate\Support\Str::uuid().'.mp4';
        Storage::disk('local')->put('scheduled-videos/'.$name, 'test');
        $event->scheduledVideo->update(['path' => 'scheduled-videos/'.$name]);
        config(['streaming.x_accel_media' => true]);
        $this->get(route('scheduled.media', $event))->assertNotFound();
        $this->travel(11)->minutes();
        $this->get(route('scheduled.media', $event))->assertOk()
            ->assertHeader('X-Accel-Redirect', '/_protected_media/scheduled-videos/'.$name);
        $event->update(['estado' => 'cancelado']);
        $this->get(route('scheduled.media', $event))->assertNotFound();
    }
    public function test_real_mp4_upload_creates_private_scheduled_event(): void {
        $event = $this->event();
        Storage::fake('public');
        $this->actingAs(User::where('role','admin')->first());
        $path = tempnam(sys_get_temp_dir(), 'ueb-scheduled-');
        try {
            $process = new \Symfony\Component\Process\Process(['/usr/bin/ffmpeg','-y','-f','lavfi','-i','color=c=blue:s=64x64:r=10','-t','2','-c:v','libx264','-pix_fmt','yuv420p','-f','mp4',$path]);
            $process->mustRun();
            $file = new \Illuminate\Http\UploadedFile($path, 'programa.mp4', 'video/mp4', null, true);
            $this->post(route('admin.scheduled.store'), ['titulo'=>'Video subido','categoria_id'=>$event->categoria_id,'fecha_inicio'=>now()->addMinutes(10)->format('Y-m-d\TH:i'),'video'=>$file,'miniatura'=>$this->poster()])->assertSessionHasNoErrors()->assertRedirect(route('admin.scheduled.index'));
            $created = Evento::where('titulo','Video subido')->firstOrFail();
            $this->assertNotNull($created->miniatura);
            Storage::disk('public')->assertExists($created->miniatura);
            $this->get(route('eventos.index'))->assertOk()->assertSee('storage/'.$created->miniatura, false);
            $this->assertNotNull($created->scheduledVideo);
            $this->assertNull($created->transmision);
            $this->assertSame(2, $created->scheduledVideo->duration);
            Storage::disk('local')->assertExists($created->scheduledVideo->path);
            $this->assertSame('pending', $created->scheduledVideo->processing_status);
            $this->get(route('scheduled.state', $created))->assertJsonPath('state', 'processing');
            app(\App\Services\ScheduledVideoProcessor::class)->process($created->scheduledVideo->id);
            $this->assertSame('ready', $created->scheduledVideo->fresh()->processing_status);
            $this->assertSame(100, $created->scheduledVideo->fresh()->processing_progress);
            $this->assertNotNull($created->scheduledVideo->fresh()->processing_heartbeat_at);
            // The uploaded original is kept with its fingerprint (preservation policy).
            $fresh = $created->scheduledVideo->fresh();
            Storage::disk('local')->assertExists($fresh->source_path);
            $this->assertSame(hash_file('sha256', Storage::disk('local')->path($fresh->source_path)), $fresh->source_sha256);
            $this->assertSame(hash_file('sha256', Storage::disk('local')->path($fresh->path)), $fresh->output_sha256);
            Storage::disk('local')->assertExists($created->scheduledVideo->fresh()->path);
        } finally { if (is_file($path)) unlink($path); }
    }
    public function test_progress_endpoint_is_admin_only_and_heartbeat_keeps_long_conversions_alive(): void {
        $event = $this->event();
        $video = $event->scheduledVideo;
        $video->update(['processing_status' => 'processing', 'processing_started_at' => now()->subHours(2),
            'processing_heartbeat_at' => now(), 'processing_progress' => 42]);
        $this->get(route('admin.scheduled.progress'))->assertRedirect();
        $admin = User::where('role', 'admin')->firstOrFail();
        $this->actingAs($admin)->get(route('admin.scheduled.progress'))->assertOk()
            ->assertJsonPath('events.'.$event->id.'.status', 'processing')
            ->assertJsonPath('events.'.$event->id.'.progress', 42);
        \Illuminate\Support\Facades\Artisan::call('scheduled:process');
        $this->assertSame('processing', $video->fresh()->processing_status);
        $video->update(['processing_heartbeat_at' => now()->subHours(2)]);
        \Illuminate\Support\Facades\Artisan::call('scheduled:process');
        $this->assertSame('failed', $video->fresh()->processing_status);
    }
    public function test_common_formats_are_converted_and_failures_can_only_be_retried_by_admin(): void {
        $event = $this->event();
        $admin = User::where('role', 'admin')->first();
        $this->actingAs($admin);
        foreach (['webm' => ['webm', 'libvpx-vp9'], 'mov' => ['mov', 'mpeg4'], 'mkv' => ['matroska', 'mpeg4'], 'avi' => ['avi', 'mpeg4'], 'wmv' => ['asf', 'wmv2'], 'mpeg' => ['mpeg', 'mpeg2video']] as $extension => [$format, $codec]) {
            $path = tempnam(sys_get_temp_dir(), 'ueb-format-');
            try {
                $args = ['/usr/bin/ffmpeg','-y','-f','lavfi','-i','testsrc2=s=160x120:r=25'];
                if ($extension === 'webm') array_push($args, '-f','lavfi','-i','sine=frequency=1000', '-c:a','libopus');
                (new \Symfony\Component\Process\Process(array_merge($args, ['-t','2','-c:v',$codec,'-f',$format,$path])))->mustRun();
                $file = new \Illuminate\Http\UploadedFile($path, 'programa.'.$extension, null, null, true);
                $this->post(route('admin.scheduled.store'), ['titulo'=>$extension,'categoria_id'=>$event->categoria_id,'fecha_inicio'=>now()->addDay()->format('Y-m-d\TH:i'),'video'=>$file])->assertSessionHasNoErrors();
                $video = Evento::where('titulo', $extension)->firstOrFail()->scheduledVideo;
                app(\App\Services\ScheduledVideoProcessor::class)->process($video->id);
                $this->assertSame('ready', $video->fresh()->processing_status, $extension);
                $this->assertGreaterThan(0, $video->fresh()->optimized_bytes);
                $probe = new \Symfony\Component\Process\Process(['/usr/bin/ffprobe','-v','error','-show_entries','stream=codec_name','-of','json',Storage::disk('local')->path($video->fresh()->path)]);
                $probe->mustRun();
                $codecs = array_column(json_decode($probe->getOutput(), true)['streams'], 'codec_name');
                $this->assertContains('h264', $codecs);
                if ($extension === 'webm') $this->assertContains('aac', $codecs);
            } finally { if (is_file($path)) unlink($path); }
        }
        $broken = $event->scheduledVideo;
        $broken->update(['processing_status'=>'pending', 'source_path'=>'missing.mp4']);
        app(\App\Services\ScheduledVideoProcessor::class)->process($broken->id);
        $this->assertSame('failed', $broken->fresh()->processing_status);
        $this->get(route('scheduled.media', $event))->assertNotFound();
        $this->actingAs(User::factory()->create(['role'=>'estudiante']))->post(route('admin.scheduled.retry', $event))->assertForbidden();
        $this->travel(61)->seconds();
        $this->actingAs($admin)->post(route('admin.scheduled.retry', $event))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('pending', $broken->fresh()->processing_status);
    }

}
