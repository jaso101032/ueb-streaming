<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Evento;
use App\Models\InstitutionalAd;
use App\Models\User;
use App\Services\InstitutionalAdProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class InstitutionalAdTest extends TestCase
{
    use RefreshDatabase;

    private function ad(string $title, int $duration): InstitutionalAd
    {
        $path = 'institutional-ads/'.$title.'.mp4';
        Storage::disk('local')->put($path, 'test');

        return InstitutionalAd::create(['title' => $title, 'path' => $path,
            'duration' => $duration, 'processing_status' => 'ready',
            'ready_at' => now(), 'is_active' => true]);
    }

    public function test_advertisements_fill_gaps_rotate_and_yield_to_programming(): void
    {
        Storage::fake('local');
        $this->freezeTime();
        $first = $this->ad('Primero', 10);
        $second = $this->ad('Segundo', 20);

        $this->get(route('channel.state'))->assertOk()->assertJsonPath('mode', 'advertisement')
            ->assertJsonPath('advertisement.id', $first->id)->assertJsonPath('playback.position', 0)
            ->assertJsonPath('summary.totalSeconds', 0);
        $this->travel(11)->seconds();
        $this->get(route('channel.state'))->assertJsonPath('advertisement.id', $second->id)
            ->assertJsonPath('playback.position', 1);

        $user = User::factory()->create(['role' => 'admin']);
        $category = Categoria::create(['nombre' => 'General', 'slug' => 'general']);
        $event = Evento::create(['titulo' => 'Programa', 'slug' => 'programa',
            'organizador_id' => $user->id, 'categoria_id' => $category->id,
            'tipo_evento' => 'Emisión pregrabada', 'estado' => 'programado',
            'fecha_inicio' => now()->addSeconds(4)]);
        $event->scheduledVideo()->create(['path' => 'scheduled-videos/programa.mp4', 'duration' => 10]);
        $this->travel(4)->seconds();
        $this->get(route('channel.state'))->assertJsonPath('mode', 'scheduled')->assertJsonPath('event.id', $event->id);
        $this->travel(10)->seconds();
        $this->get(route('channel.state'))->assertJsonPath('mode', 'advertisement')
            ->assertJsonPath('advertisement.id', $second->id);
        $this->travel(5)->seconds();
        $this->get(route('channel.state'))->assertJsonPath('advertisement.id', $first->id);
    }

    public function test_only_admin_can_upload_and_ad_can_be_disabled(): void
    {
        Storage::fake('local');
        $ad = $this->ad('Institucional', 15);
        $viewer = User::factory()->create(['role' => 'estudiante']);
        $this->actingAs($viewer)->post(route('admin.ads.store'), [])->assertForbidden();
        $this->post(route('admin.ads.toggle', $ad))->assertForbidden();

        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get(route('admin.scheduled.index'))->assertOk()
            ->assertSee('Institucional');
        $this->post(route('admin.ads.store'), [])->assertSessionHasErrors(['ad_title', 'ad_video']);
        $this->post(route('admin.ads.toggle', $ad))->assertRedirect();
        $this->get(route('channel.state'))->assertJsonPath('mode', 'idle');
        $this->get(route('channel.ad.media', $ad))->assertNotFound();
        $this->post(route('admin.ads.toggle', $ad))->assertRedirect();
        $this->get(route('channel.state'))->assertJsonPath('mode', 'advertisement');
        $this->get(route('channel.ad.media', $ad))->assertOk();
    }

    public function test_production_media_handoff_still_checks_that_the_ad_is_active(): void
    {
        Storage::fake('local');
        $name = \Illuminate\Support\Str::uuid().'.mp4';
        Storage::disk('local')->put('institutional-ads/'.$name, 'test');
        $ad = InstitutionalAd::create(['title' => 'Aviso UEB', 'path' => 'institutional-ads/'.$name,
            'duration' => 15, 'processing_status' => 'ready', 'ready_at' => now(), 'is_active' => true]);
        config(['streaming.x_accel_media' => true]);
        $this->get(route('channel.ad.media', $ad))->assertOk()
            ->assertHeader('X-Accel-Redirect', '/_protected_media/institutional-ads/'.$name);
        $ad->update(['is_active' => false]);
        $this->get(route('channel.ad.media', $ad))->assertNotFound();
    }

    public function test_live_signal_interrupts_advertising_and_advertising_returns_after_disconnect(): void
    {
        Storage::fake('local');
        $this->ad('Institucional', 20);
        $admin = User::factory()->create(['role' => 'admin']);
        $category = Categoria::create(['nombre' => 'General', 'slug' => 'general']);
        $event = Evento::create(['titulo' => 'Directo', 'slug' => 'directo',
            'organizador_id' => $admin->id, 'categoria_id' => $category->id,
            'tipo_evento' => 'Conferencia', 'estado' => 'programado', 'fecha_inicio' => now()]);
        $event->transmision()->create(['stream_key' => 'ueb_directo',
            'rtmp_server' => 'rtmp://localhost/live', 'hls_url' => 'http://localhost/hls/directo.m3u8', 'activo' => false]);
        config(['streaming.callback_token' => 'ads-test']);

        $this->get(route('channel.state'))->assertJsonPath('mode', 'advertisement');
        $this->post('/streaming/hooks/authorize?token=ads-test', [
            'app' => 'live', 'name' => 'ueb_directo', 'call' => 'publish', 'clientid' => '1',
        ])->assertNoContent();
        $this->get(route('channel.state'))->assertJsonPath('mode', 'live')
            ->assertJsonPath('advertisement', null);
        $this->post('/streaming/hooks/disconnected?token=ads-test', [
            'app' => 'live', 'name' => 'ueb_directo', 'clientid' => '1',
        ])->assertNoContent();
        $this->get(route('channel.state'))->assertJsonPath('mode', 'advertisement');
    }

    public function test_uploaded_ad_is_transcoded_and_enters_the_channel(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'admin']);
        $path = tempnam(sys_get_temp_dir(), 'ueb-ad-');
        try {
            (new Process(['/usr/bin/ffmpeg', '-y', '-f', 'lavfi', '-i', 'color=c=blue:s=64x64:r=10',
                '-t', '2', '-c:v', 'libx264', '-pix_fmt', 'yuv420p', '-f', 'mp4', $path]))->mustRun();
            $this->actingAs($admin)->withHeaders(['Accept' => 'application/json'])->post(route('admin.ads.store'), [
                'ad_title' => 'Campaña UEB', 'ad_video' => new UploadedFile($path, 'campana.mp4', 'video/mp4', null, true),
            ])->assertCreated()->assertJsonPath('redirect', route('admin.scheduled.index').'#schedule-ads');
            $ad = InstitutionalAd::firstOrFail();
            $this->assertSame('pending', $ad->processing_status);
            $this->get(route('channel.state'))->assertJsonPath('mode', 'idle');
            app(InstitutionalAdProcessor::class)->process($ad->id);
            $ad->refresh();
            $this->assertSame('ready', $ad->processing_status);
            $this->assertSame(100, $ad->processing_progress);
            $this->assertNotNull($ad->processing_heartbeat_at);
            $this->assertNull($ad->source_path);
            Storage::disk('local')->assertExists($ad->path);
            $this->get(route('channel.state'))->assertJsonPath('mode', 'advertisement')
                ->assertJsonPath('advertisement.title', 'Campaña UEB');
        } finally {
            if (is_file($path)) unlink($path);
        }
    }
}
