<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Categoria;
use App\Models\Evento;
use App\Models\User;
use App\Services\ChannelSchedule;
use App\Services\ScheduledVideoProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/** Videos subidos solo para el catálogo: se ven a demanda y nunca entran al canal 24/7. */
class CatalogVideoTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Categoria $category;

    private string $file;

    protected function setUp(): void
    {
        parent::setUp();
        if (! is_executable(config('streaming.ffmpeg'))) $this->markTestSkipped('FFmpeg no está instalado.');
        Storage::fake('local');
        Storage::fake('public');
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->category = Categoria::create(['nombre' => 'Cultural', 'slug' => 'cultural']);
        $this->file = tempnam(sys_get_temp_dir(), 'ueb-catalog-');
        (new Process([config('streaming.ffmpeg'), '-y', '-loglevel', 'error', '-f', 'lavfi', '-i', 'color=c=blue:s=64x64:r=10', '-t', '2',
            '-c:v', 'libx264', '-pix_fmt', 'yuv420p', '-f', 'mp4', $this->file]))->mustRun();
    }

    protected function tearDown(): void
    {
        @unlink($this->file);
        parent::tearDown();
    }

    private function upload(array $data = []): \Illuminate\Testing\TestResponse
    {
        return $this->post(route('admin.media.videos.store'), $data + [
            'titulo' => 'Documental de Guaranda', 'categoria_id' => $this->category->id, 'visibilidad' => 'publico',
            'descripcion' => 'Un recorrido por la ciudad.',
            'video' => new UploadedFile($this->file, 'documental.mp4', 'video/mp4', null, true),
        ]);
    }

    public function test_only_admins_can_upload_catalog_videos(): void
    {
        foreach (['operador', 'estudiante'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            $this->upload()->assertForbidden();
        }
        $this->actingAs($this->admin);
        $this->upload(['visibilidad' => 'secreto'])->assertSessionHasErrors('visibilidad');
        $this->assertSame(0, Evento::count());
    }

    public function test_catalog_video_is_prepared_then_appears_on_demand_without_entering_the_channel(): void
    {
        $this->actingAs($this->admin);
        $this->upload()->assertSessionHasNoErrors()->assertRedirect(route('admin.media.index'))
            ->assertSessionHas('success', fn ($m) => str_contains($m, 'aparecerá en el catálogo'));

        $event = Evento::where('titulo', 'Documental de Guaranda')->firstOrFail();
        $video = $event->scheduledVideo;
        $this->assertFalse($video->on_channel);
        $this->assertSame('pending', $video->processing_status);
        $this->assertSame('finalizado', $event->estado);
        $this->assertSame(1, AuditLog::where('action', 'video.subido')->count());

        // While it is being prepared only the team sees it.
        auth()->logout();
        $this->get(route('eventos.index'))->assertOk()->assertDontSee('Documental de Guaranda');
        $this->get(route('eventos.show', $event))->assertNotFound();

        app(ScheduledVideoProcessor::class)->process($video->id);
        $this->assertSame('ready', $video->fresh()->processing_status);

        // Ready: in the catalog (also under "Anteriores"), playable as a normal video.
        $this->get(route('eventos.index'))->assertOk()->assertSee('Documental de Guaranda');
        $this->get(route('eventos.index', ['filtro' => 'anteriores']))->assertOk()->assertSee('Documental de Guaranda');
        $this->get(route('eventos.show', $event))->assertOk()->assertSee(route('scheduled.media', $event), false)
            ->assertDontSee(route('scheduled.state', $event), false);
        $this->get(route('scheduled.media', $event))->assertOk();
        $this->get(route('scheduled.state', $event))->assertJsonPath('state', 'ended');

        // Never on the 24/7 channel, its schedule or the Parrilla page.
        $this->assertArrayNotHasKey($event->id, app(ChannelSchedule::class)->timeline());
        $this->actingAs($this->admin);
        $this->get(route('admin.scheduled.index'))->assertOk()->assertDontSee('Documental de Guaranda');
        $this->get(route('admin.media.index', ['kind' => 'catalog']))->assertOk()->assertSee('Documental de Guaranda');
        $this->get(route('admin.eventos.show', $event))->assertOk()->assertSee('&quot;catalogVideo&quot;', false);

        // Qualities and automatic subtitles are queued like any stored video.
        $this->assertSame(1, app(\App\Services\AdaptiveRenditions::class)->candidates(\App\Models\ScheduledVideo::class)->count());
    }

    public function test_team_only_catalog_videos_are_hidden_from_the_audience(): void
    {
        $this->actingAs($this->admin);
        $this->upload(['titulo' => 'Reunión interna', 'visibilidad' => 'restringido'])->assertSessionHasNoErrors();
        $event = Evento::where('titulo', 'Reunión interna')->firstOrFail();
        app(ScheduledVideoProcessor::class)->process($event->scheduledVideo->id);

        $this->actingAs(User::factory()->create(['role' => 'estudiante']));
        $this->get(route('eventos.index'))->assertOk()->assertDontSee('Reunión interna');
        $this->get(route('eventos.show', $event))->assertNotFound();
    }

    public function test_faculty_and_career_group_videos_and_build_a_playlist(): void
    {
        $faculty = \App\Models\Facultad::create(['nombre' => 'FACAGEI', 'slug' => 'facagei']);
        $software = \App\Models\Carrera::create(['nombre' => 'Software', 'slug' => 'software', 'facultad_id' => $faculty->id]);
        $other = \App\Models\Facultad::create(['nombre' => 'Salud', 'slug' => 'salud']);
        $nursing = \App\Models\Carrera::create(['nombre' => 'Enfermería', 'slug' => 'enfermeria', 'facultad_id' => $other->id]);
        $this->actingAs($this->admin);

        // The career must belong to the chosen faculty.
        $this->upload(['facultad_id' => $faculty->id, 'carrera_id' => $nursing->id])->assertSessionHasErrors('carrera_id');
        $this->upload(['carrera_id' => $software->id])->assertSessionHasErrors('carrera_id');

        foreach (['Clase 1 de Software', 'Clase 2 de Software'] as $title) {
            $this->upload(['titulo' => $title, 'facultad_id' => $faculty->id, 'carrera_id' => $software->id])->assertSessionHasNoErrors();
        }
        $this->upload(['titulo' => 'Sin carrera'])->assertSessionHasNoErrors();
        foreach (Evento::with('scheduledVideo')->get() as $event) app(ScheduledVideoProcessor::class)->process($event->scheduledVideo->id);
        $first = Evento::where('titulo', 'Clase 1 de Software')->firstOrFail();
        $this->assertSame($software->id, $first->carrera_id);

        auth()->logout();
        $this->get(route('eventos.index', ['facultad_id' => $faculty->id, 'carrera_id' => $software->id]))->assertOk()
            ->assertSee('Clase 2 de Software')->assertDontSee('Sin carrera');
        $this->get(route('eventos.show', $first))->assertOk()->assertSee('Más de Software')->assertSee('Clase 2 de Software')
            ->assertSee('carrera_id='.$software->id, false);
    }

    public function test_admin_edits_an_uploaded_video_without_replacing_the_file(): void
    {
        $this->actingAs($this->admin);
        $this->upload(['miniatura' => UploadedFile::fake()->image('portada.jpg', 64, 36)])->assertSessionHasNoErrors();
        $event = Evento::firstOrFail();
        $path = $event->scheduledVideo->path;
        $thumbnail = $event->miniatura;
        Storage::disk('public')->assertExists($thumbnail);
        $this->get(route('admin.eventos.show', $event))->assertOk()->assertSee('&quot;videoEdit&quot;', false);

        $this->actingAs(User::factory()->create(['role' => 'operador']))
            ->put(route('admin.media.videos.update', $event), ['titulo' => 'X', 'categoria_id' => $this->category->id, 'visibilidad' => 'publico'])->assertForbidden();

        $this->actingAs($this->admin)->put(route('admin.media.videos.update', $event), [
            'titulo' => 'Documental editado', 'descripcion' => 'Nueva descripción', 'categoria_id' => $this->category->id,
            'visibilidad' => 'interno', 'quitar_miniatura' => '1',
        ])->assertSessionHasNoErrors()->assertRedirect(route('admin.eventos.show', $event));

        $event->refresh();
        $this->assertSame('Documental editado', $event->titulo);
        $this->assertSame('interno', $event->visibilidad);
        $this->assertNull($event->miniatura);
        Storage::disk('public')->assertMissing($thumbnail);
        $this->assertSame($path, $event->scheduledVideo->path);
        $this->assertSame(1, AuditLog::where('action', 'video.actualizado')->count());
    }
}
