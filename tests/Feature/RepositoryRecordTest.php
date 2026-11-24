<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Evento;
use App\Models\ScheduledVideo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** El expediente de repositorio recalcula las huellas y detecta alteraciones. */
class RepositoryRecordTest extends TestCase
{
    use RefreshDatabase;

    private function catalogVideo(string $original, string $published): Evento
    {
        Storage::fake('local');
        Storage::disk('local')->put('scheduled-videos/originals/clase.mov', $original);
        Storage::disk('local')->put('scheduled-videos/clase.mp4', $published);
        $admin = User::factory()->create(['role' => 'admin']);
        $category = Categoria::create(['nombre' => 'Académico '.uniqid(), 'slug' => 'academico-'.uniqid()]);
        $event = Evento::create(['titulo' => 'Clase magistral', 'slug' => 'clase-'.uniqid(), 'estado' => 'finalizado',
            'categoria_id' => $category->id, 'organizador_id' => $admin->id, 'tipo_evento' => 'Conferencias',
            'fecha_inicio' => now()->subDay(), 'visibilidad' => 'publico', 'descripcion' => 'Clase de prueba']);
        ScheduledVideo::create(['evento_id' => $event->id, 'on_channel' => false, 'path' => 'scheduled-videos/clase.mp4',
            'source_path' => 'scheduled-videos/originals/clase.mov', 'duration' => 60, 'processing_status' => 'ready',
            'source_sha256' => hash('sha256', 'original'), 'output_sha256' => hash('sha256', 'publicado')]);

        return $event;
    }

    public function test_record_lists_metadata_authorization_and_matching_fingerprints(): void
    {
        $event = $this->catalogVideo('original', 'publicado');

        $this->assertSame(0, Artisan::call('uebtv:expediente', ['evento' => [$event->id], '--json' => true]));
        $record = json_decode(Artisan::output(), true)[0];

        $this->assertSame('Clase magistral', $record['titulo']);
        $this->assertSame('vigente', $record['autorizacion']['estado']);
        $this->assertSame('archivo cargado (catálogo)', $record['metadatos']['origen']);
        $files = collect($record['archivos'])->keyBy('tipo');
        $this->assertSame(hash('sha256', 'original'), $files['original cargado']['sha256']);
        $this->assertSame('sí', $files['original cargado']['coincide']);
        $this->assertSame(strlen('publicado'), $files['derivado MP4']['bytes']);
        $this->assertSame('sí', $files['derivado MP4']['coincide']);
    }

    public function test_record_flags_altered_files(): void
    {
        $event = $this->catalogVideo('original', 'alterado');

        $this->assertSame(0, Artisan::call('uebtv:expediente', ['evento' => [$event->id], '--json' => true]));
        $files = collect(json_decode(Artisan::output(), true)[0]['archivos'])->keyBy('tipo');

        $this->assertSame('NO', $files['derivado MP4']['coincide']);
    }

    public function test_unknown_event_fails(): void
    {
        $this->artisan('uebtv:expediente', ['evento' => [999999]])->assertFailed();
    }
}
