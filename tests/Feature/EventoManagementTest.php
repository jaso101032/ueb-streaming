<?php

namespace Tests\Feature;

use App\Models\Carrera;
use App\Models\Categoria;
use App\Models\Evento;
use App\Models\Facultad;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EventoManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Categoria $categoria;

    private Facultad $facultad;

    private Carrera $carrera;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->categoria = Categoria::create(['nombre' => 'Académico', 'slug' => 'academico']);
        $this->facultad = Facultad::create(['nombre' => 'Ingeniería', 'slug' => 'ingenieria']);
        $this->carrera = Carrera::create(['nombre' => 'Software', 'slug' => 'software', 'facultad_id' => $this->facultad->id]);
        $this->actingAs($this->admin);
    }

    private function data(array $overrides = []): array
    {
        return array_replace([
            'titulo' => 'Conferencia de prueba',
            'tipo_evento' => 'Conferencias',
            'categoria_id' => $this->categoria->id,
            'facultad_id' => $this->facultad->id,
            'carrera_id' => $this->carrera->id,
            'fecha_inicio' => '2026-10-01T10:00',
            'fecha_fin' => '2026-10-01T12:00',
            'lugar' => 'Auditorio',
        ], $overrides);
    }

    private function image(int $width = 1): UploadedFile
    {
        // PNG real sin depender de la extensión GD en el entorno de pruebas.
        $chunk = fn (string $type, string $data) => pack('N', strlen($data)).$type.$data.pack('N', crc32($type.$data));
        $png = "\x89PNG\r\n\x1a\n"
            .$chunk('IHDR', pack('NNCCCCC', $width, 1, 8, 2, 0, 0, 0))
            .$chunk('IDAT', gzcompress("\0".str_repeat("\xff\0\0", $width)))
            .$chunk('IEND', '');

        return UploadedFile::fake()->createWithContent('poster.png', $png);
    }

    private function evento(): Evento
    {
        return Evento::create($this->data(['slug' => 'evento-prueba', 'organizador_id' => $this->admin->id, 'estado' => 'programado']));
    }

    public function test_forms_show_catalogs_and_admin_navigation(): void
    {
        $this->get(route('admin.eventos.index'))->assertOk()->assertSee('Nuevo Evento');
        $this->get(route('admin.eventos.create'))->assertOk()->assertSee('Académico')->assertSee('Ingeniería')->assertSee('Software')->assertSee('multipart/form-data', false);
        $evento = $this->evento();
        $this->get(route('admin.eventos.edit', $evento))->assertOk()->assertSee('2026-10-01T12:00');
        $this->get(route('admin.eventos.show', $evento))->assertOk()->assertSee('Conferencia de prueba')->assertSee('Software');
    }

    public function test_admin_can_create_event_with_thumbnail_and_transmission(): void
    {
        $response = $this->post(route('admin.eventos.store'), $this->data(['miniatura' => $this->image()]));
        $response->assertSessionHasNoErrors();
        $evento = Evento::firstOrFail();
        $response->assertRedirect(route('admin.eventos.show', $evento));
        $this->assertSame('programado', $evento->estado);
        $this->assertSame($this->admin->id, $evento->organizador_id);
        $this->assertNotNull($evento->transmision);
        Storage::disk('public')->assertExists($evento->miniatura);
        $this->get(route('eventos.index'))->assertOk()->assertSee('storage/'.$evento->miniatura, false);
    }

    public function test_operator_can_manage_and_soft_delete_events(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'operador']));
        $this->post(route('admin.eventos.store'), $this->data())->assertSessionHasNoErrors();
        $evento = Evento::firstOrFail();
        $this->put(route('admin.eventos.update', $evento), $this->data(['titulo' => 'Actualizado', 'estado' => 'finalizado']))->assertRedirect(route('admin.eventos.show', $evento));
        $this->assertSame('Actualizado', $evento->fresh()->titulo);
        $this->delete(route('admin.eventos.destroy', $evento))->assertRedirect(route('admin.eventos.index'));
        $this->assertSoftDeleted('eventos', ['id' => $evento->id]);
    }

    public function test_partial_update_preserves_omitted_fields_thumbnail_and_slug(): void
    {
        $evento = $this->evento();
        $evento->update(['miniatura' => 'eventos/miniaturas/existing.jpg']);
        $this->patch(route('admin.eventos.update', $evento), [
            'titulo' => 'Nuevo título', 'tipo_evento' => 'Conferencias',
            'categoria_id' => $this->categoria->id, 'fecha_inicio' => '2026-10-01T11:00',
        ])->assertSessionHasNoErrors();
        $evento->refresh();
        $this->assertSame($this->facultad->id, $evento->facultad_id);
        $this->assertSame($this->carrera->id, $evento->carrera_id);
        $this->assertSame('2026-10-01 12:00', $evento->fecha_fin->format('Y-m-d H:i'));
        $this->assertSame('Auditorio', $evento->lugar);
        $this->assertSame('evento-prueba', $evento->slug);
        $this->assertSame('eventos/miniaturas/existing.jpg', $evento->miniatura);
    }

    public function test_optional_fields_can_be_cleared_explicitly(): void
    {
        $evento = $this->evento();
        $this->put(route('admin.eventos.update', $evento), $this->data(['facultad_id' => '', 'carrera_id' => '', 'fecha_fin' => '']))->assertSessionHasNoErrors();
        $evento->refresh();
        $this->assertNull($evento->facultad_id);
        $this->assertNull($evento->carrera_id);
        $this->assertNull($evento->fecha_fin);
    }

    public function test_invalid_dates_relations_and_states_are_rejected_on_create_and_update(): void
    {
        $evento = $this->evento();
        $other = Facultad::create(['nombre' => 'Otra facultad', 'slug' => 'otra']);
        foreach ([
            ['fecha_fin' => '2026-10-01T09:00'],
            ['fecha_fin' => '2026-10-01T10:00'],
            ['facultad_id' => $other->id],
            ['facultad_id' => null],
            ['estado' => 'invalido'],
            ['categoria_id' => 99999],
            ['video_url' => 'javascript:alert(1)'],
            ['video_url' => 'https://example.com/'.str_repeat('a', 256)],
        ] as $invalid) {
            $this->post(route('admin.eventos.store'), $this->data($invalid))->assertSessionHasErrors();
            $this->put(route('admin.eventos.update', $evento), $this->data($invalid))->assertSessionHasErrors();
        }
        $this->assertDatabaseCount('eventos', 1);
        $this->assertSame('programado', $evento->fresh()->estado);
    }

    public function test_changing_start_cannot_leave_an_earlier_preserved_end(): void
    {
        $evento = $this->evento();
        $data = $this->data(['fecha_inicio' => '2026-10-02T10:00']);
        unset($data['fecha_fin']);
        $this->patch(route('admin.eventos.update', $evento), $data)->assertSessionHasErrors('fecha_fin');
    }

    public function test_replacing_thumbnail_removes_old_file_after_saving(): void
    {
        $evento = $this->evento();
        Storage::disk('public')->put('eventos/miniaturas/old.jpg', 'old');
        $evento->update(['miniatura' => 'eventos/miniaturas/old.jpg']);
        $this->put(route('admin.eventos.update', $evento), $this->data(['miniatura' => $this->image()]))->assertSessionHasNoErrors();
        Storage::disk('public')->assertExists($evento->fresh()->miniatura);
        Storage::disk('public')->assertMissing('eventos/miniaturas/old.jpg');
    }

    public function test_invalid_uploads_do_not_create_events_or_files(): void
    {
        foreach ([UploadedFile::fake()->create('script.svg', 10, 'image/svg+xml'), $this->image()->size(2049), $this->image(4097)] as $file) {
            $this->post(route('admin.eventos.store'), $this->data(['miniatura' => $file]))->assertSessionHasErrors('miniatura');
        }
        $this->assertDatabaseCount('eventos', 0);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_regular_user_cannot_access_or_mutate_events(): void
    {
        $evento = $this->evento();
        $this->actingAs(User::factory()->create(['role' => 'usuario']));
        foreach (['index', 'create', 'show', 'edit'] as $action) {
            $this->get(route('admin.eventos.'.$action, in_array($action, ['show', 'edit']) ? $evento : []))->assertForbidden();
        }
        $this->post(route('admin.eventos.store'), $this->data())->assertForbidden();
        $this->put(route('admin.eventos.update', $evento), $this->data())->assertForbidden();
        $this->delete(route('admin.eventos.destroy', $evento))->assertForbidden();
        $this->assertDatabaseCount('eventos', 1);
        $this->assertNotSoftDeleted($evento);
    }

    public function test_repeated_titles_have_unique_slugs(): void
    {
        $this->post(route('admin.eventos.store'), $this->data())->assertSessionHasNoErrors();
        $this->post(route('admin.eventos.store'), $this->data())->assertSessionHasNoErrors();
        $this->assertSame(2, Evento::pluck('slug')->unique()->count());
    }

    public function test_catalog_seeders_are_repeatable(): void
    {
        $this->seed();
        $counts = [Categoria::count(), Facultad::count(), Carrera::count()];
        $this->seed();
        $this->assertSame($counts, [Categoria::count(), Facultad::count(), Carrera::count()]);
        $this->assertDatabaseMissing('users', ['email' => 'test@example.com']);
    }
}
