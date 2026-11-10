<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Evento;
use App\Models\InstitutionalAd;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/** RNF-11: subtítulos para videos almacenados, con los mismos permisos que el video. */
class SubtitlesTest extends TestCase
{
    use RefreshDatabase;

    private const SRT = "1\r\n00:00:01,000 --> 00:00:03,500\r\nBienvenidos a <b>UEBTV</b><script>alert(1)</script>\r\n\r\n2\r\n00:00:04,000 --> 00:00:06,000\r\nSegunda línea\r\n";

    private string $root;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->root = sys_get_temp_dir().'/uebtv-subtitles-'.Str::uuid();
        mkdir($this->root.'/vod', 0700, true);
        config(['streaming.recordings_root' => $this->root]);
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->root.'/vod/*') ?: []);
        @rmdir($this->root.'/vod');
        @rmdir($this->root);
        parent::tearDown();
    }

    private function recordedEvent(string $publication = 'pendiente'): Evento
    {
        $category = Categoria::firstOrCreate(['slug' => 'general'], ['nombre' => 'General']);
        $event = Evento::create(['titulo' => 'Grabación', 'slug' => 'grabacion-'.Str::uuid(), 'tipo_evento' => 'Conferencia',
            'categoria_id' => $category->id, 'organizador_id' => $this->admin->id, 'fecha_inicio' => now(), 'estado' => 'finalizado']);
        $file = Str::uuid().'.mp4';
        file_put_contents($this->root.'/vod/'.$file, 'mp4');
        $event->transmision()->create(['stream_key' => 'ueb_'.Str::random(12), 'rtmp_server' => 'r', 'recording_status' => 'ready',
            'recording_file' => $file, 'publicacion' => $publication]);

        return $event;
    }

    public function test_srt_is_converted_sanitized_and_served_with_the_recording_permissions(): void
    {
        $event = $this->recordedEvent();
        $this->actingAs($this->admin)->get(route('admin.eventos.show', $event))->assertOk()
            ->assertSee(route('admin.subtitles.store', $event), false);
        $this->post(route('admin.subtitles.store', $event), ['subtitles' => UploadedFile::fake()->createWithContent('clase.srt', self::SRT)])
            ->assertSessionHasNoErrors()->assertSessionHas('success');
        $path = $event->transmision->fresh()->subtitles_path;
        $vtt = Storage::disk('local')->get($path);
        $this->assertStringStartsWith("WEBVTT\n\n00:00:01.000 --> 00:00:03.500\n", $vtt);
        $this->assertStringContainsString('<b>UEBTV</b>', $vtt);
        $this->assertStringNotContainsString('<script>', $vtt);
        $this->assertDatabaseHas('audit_logs', ['action' => 'subtitulos.agregados', 'subject_id' => $event->id]);

        // Staff can preview before approval; the public cannot.
        $this->get(route('eventos.subtitles', $event))->assertOk()->assertHeader('Content-Type', 'text/vtt; charset=utf-8');
        auth()->logout();
        $this->get(route('eventos.subtitles', $event))->assertNotFound();
        $event->transmision->update(['publicacion' => 'aprobada']);
        $this->get(route('eventos.show', $event))->assertOk()->assertSee('/eventos/'.$event->id.'/subtitulos.vtt', false);
        $this->assertSame($vtt, $this->get(route('eventos.subtitles', $event))->assertOk()->getContent());
        $event->transmision->update(['publicacion' => 'retirada']);
        $this->get(route('eventos.subtitles', $event))->assertNotFound();
        $event->transmision->update(['publicacion' => 'aprobada']);
        $event->update(['visibilidad' => 'interno']);
        $this->get(route('eventos.subtitles', $event))->assertNotFound();

        $this->actingAs($this->admin)->delete(route('admin.subtitles.destroy', $event))->assertSessionHas('success');
        Storage::disk('local')->assertMissing($path);
        $this->assertNull($event->transmision->fresh()->subtitles_path);
        $this->get(route('eventos.subtitles', $event))->assertNotFound();
    }

    public function test_invalid_files_and_non_administrators_are_rejected(): void
    {
        $event = $this->recordedEvent('aprobada');
        $this->actingAs($this->admin);
        $this->post(route('admin.subtitles.store', $event), ['subtitles' => UploadedFile::fake()->createWithContent('notas.srt', 'sin tiempos')])
            ->assertSessionHasErrors('subtitles');
        $this->post(route('admin.subtitles.store', $event), ['subtitles' => UploadedFile::fake()->createWithContent('video.mp4', self::SRT)])
            ->assertSessionHasErrors('subtitles');
        $this->assertNull($event->transmision->fresh()->subtitles_path);

        $operator = User::factory()->create(['role' => 'operador', 'operator_permissions' => User::operatorActions()]);
        $this->actingAs($operator)->post(route('admin.subtitles.store', $event), ['subtitles' => UploadedFile::fake()->createWithContent('a.vtt', "WEBVTT\n\n00:01.000 --> 00:02.000\nHola\n")])
            ->assertForbidden();
    }

    public function test_scheduled_programs_and_advertising_accept_subtitles_for_the_channel(): void
    {
        $category = Categoria::firstOrCreate(['slug' => 'general'], ['nombre' => 'General']);
        $event = Evento::create(['titulo' => 'Programa', 'slug' => 'programa', 'tipo_evento' => 'Emisión pregrabada',
            'categoria_id' => $category->id, 'organizador_id' => $this->admin->id, 'fecha_inicio' => now()->subSecond(), 'estado' => 'programado']);
        Storage::disk('local')->put('scheduled-videos/programa.mp4', 'mp4');
        $event->scheduledVideo()->create(['path' => 'scheduled-videos/programa.mp4', 'duration' => 600, 'processing_status' => 'ready', 'ready_at' => now()->subSecond()]);
        $vtt = "WEBVTT\n\n00:00:01.000 --> 00:00:02.000\nPrograma subtitulado\n";

        $this->actingAs($this->admin)->post(route('admin.subtitles.store', $event), ['subtitles' => UploadedFile::fake()->createWithContent('p.vtt', $vtt)])
            ->assertSessionHasNoErrors();
        auth()->logout();
        $this->assertSame($vtt, $this->get(route('eventos.subtitles', $event))->assertOk()->getContent());
        $this->getJson(route('channel.state'))->assertJsonPath('mode', 'scheduled')
            ->assertJsonPath('event.subtitlesUrl', '/eventos/'.$event->id.'/subtitulos.vtt');

        $event->scheduledVideo->update(['processing_status' => 'failed']);
        Storage::disk('local')->put('institutional-ads/aviso.mp4', 'mp4');
        $ad = InstitutionalAd::create(['title' => 'Aviso', 'path' => 'institutional-ads/aviso.mp4', 'duration' => 30,
            'processing_status' => 'ready', 'is_active' => true, 'ready_at' => now()]);
        $this->actingAs($this->admin)->get(route('admin.scheduled.index'))->assertOk()
            ->assertSee(str_replace('/', '\\/', route('admin.ads.subtitles.store', $ad)), false);
        $this->post(route('admin.ads.subtitles.store', $ad), ['subtitles' => UploadedFile::fake()->createWithContent('a.vtt', $vtt)])
            ->assertSessionHasNoErrors();
        auth()->logout();
        $this->getJson(route('channel.state'))->assertJsonPath('mode', 'advertisement')
            ->assertJsonPath('advertisement.subtitlesUrl', '/senal/publicidad/'.$ad->id.'/subtitulos.vtt');
        $this->get(route('channel.ad.subtitles', $ad))->assertOk();
        $ad->update(['is_active' => false]);
        $this->get(route('channel.ad.subtitles', $ad))->assertNotFound();
    }
}
