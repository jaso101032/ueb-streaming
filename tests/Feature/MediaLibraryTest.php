<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Evento;
use App\Models\InstitutionalAd;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class MediaLibraryTest extends TestCase
{
    use RefreshDatabase;

    private string $recordings;
    private User $admin;
    private Categoria $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->recordings = sys_get_temp_dir().'/uebtv-media-test-'.Str::uuid();
        File::makeDirectory($this->recordings.'/vod', 0770, true);
        config(['streaming.recordings_root' => $this->recordings]);
        Storage::fake('local');
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->category = Categoria::create(['nombre' => 'Académico', 'slug' => 'academico']);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->recordings);
        parent::tearDown();
    }

    private function event(string $title): Evento
    {
        return Evento::create([
            'titulo' => $title, 'slug' => Str::uuid(), 'categoria_id' => $this->category->id,
            'tipo_evento' => 'Conferencia', 'organizador_id' => $this->admin->id,
            'fecha_inicio' => now(), 'estado' => 'finalizado',
        ]);
    }

    public function test_only_admin_can_list_and_preview_ready_media(): void
    {
        $event = $this->event('Directo guardado');
        $filename = Str::uuid().'.mp4';
        file_put_contents($this->recordings.'/vod/'.$filename, 'recording');
        $event->transmision()->create(['stream_key' => 'test_stream', 'recording_status' => 'ready',
            'recording_file' => $filename, 'recording_metadata' => ['rawBytes' => 100, 'mp4Bytes' => 9]]);

        $scheduled = $this->event('Programación UEB');
        $scheduled->scheduledVideo()->create(['source_path' => 'scheduled-sources/source.mov',
            'path' => 'scheduled-videos/ready.mp4', 'source_bytes' => 1000,
            'optimized_bytes' => 500, 'processing_status' => 'ready', 'duration' => 60]);
        Storage::disk('local')->put('scheduled-videos/ready.mp4', 'scheduled');

        $ad = InstitutionalAd::create(['title' => 'Anuncio institucional', 'source_path' => 'ad-sources/source.mov',
            'path' => 'institutional-ads/ready.mp4', 'source_bytes' => 800,
            'optimized_bytes' => 400, 'processing_status' => 'ready', 'duration' => 30, 'is_active' => false]);
        Storage::disk('local')->put('institutional-ads/ready.mp4', 'advertisement');

        $this->get(route('admin.media.index'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create(['role' => 'operador']));
        $this->get(route('admin.media.index'))->assertForbidden();
        $this->get(route('admin.media.ad-preview', $ad))->assertForbidden();

        $this->actingAs($this->admin);
        $this->get(route('admin.media.index'))->assertOk()->assertSee('Directo guardado')
            ->assertSee('Programación UEB')->assertSee('Anuncio institucional');
        $this->get(route('admin.media.index', ['kind' => 'ad']))->assertOk()
            ->assertSee('Anuncio institucional')->assertDontSee('Directo guardado');
        $this->get(route('admin.media.index', ['categoria_id' => $this->category->id]))->assertOk()
            ->assertSee('Directo guardado')->assertDontSee('Anuncio institucional');
        $this->get(route('admin.media.index', ['q' => 'Programación']))->assertOk()
            ->assertSee('Programación UEB')->assertDontSee('Anuncio institucional');
        $this->get(route('admin.media.index', ['from' => now()->addDay()->toDateString()]))->assertOk()
            ->assertDontSee('Directo guardado');
        $this->get(route('admin.media.scheduled-preview', $scheduled))->assertOk();
        $this->get(route('admin.media.ad-preview', $ad))->assertOk();
        $this->get(route('channel.ad.media', $ad))->assertNotFound();
    }
}
