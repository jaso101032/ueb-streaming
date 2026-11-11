<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Categoria;
use App\Models\Evento;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** O-10: licencia de uso y referencia de autorización de cada evento. */
class UsageLicenseTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    private function payload(array $extra = []): array
    {
        return ['titulo' => 'Conferencia abierta', 'tipo_evento' => 'Conferencias', 'fecha_inicio' => now()->addDay()->format('Y-m-d\TH:i'),
            'categoria_id' => Categoria::firstOrCreate(['slug' => 'general'], ['nombre' => 'General'])->id] + $extra;
    }

    public function test_new_and_existing_events_default_to_institutional_use(): void
    {
        $this->actingAs($this->admin)->post(route('admin.eventos.store'), $this->payload())->assertSessionHasNoErrors();
        $event = Evento::latest('id')->firstOrFail();
        $this->assertSame('institucional', $event->licencia);
        $this->assertNull($event->autorizacion_referencia);
        $this->assertDatabaseHas('audit_logs', ['action' => 'evento.creado', 'subject_id' => $event->id]);
        $this->assertSame('institucional', AuditLog::where('action', 'evento.creado')->firstOrFail()->details['licencia']);
    }

    public function test_form_explains_each_license_to_the_creator(): void
    {
        $page = $this->actingAs($this->admin)->get(route('admin.eventos.create'))->assertOk()->getContent();
        foreach (Evento::LICENSES as $license) {
            $this->assertStringContainsString(e(json_encode($license['help'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)), $page);
        }
    }

    public function test_license_is_validated_audited_and_shown_without_exposing_the_authorization(): void
    {
        $this->actingAs($this->admin)->post(route('admin.eventos.store'), $this->payload(['licencia' => 'libre']))->assertSessionHasErrors('licencia');
        $this->post(route('admin.eventos.store'), $this->payload(['licencia' => 'otra']))->assertSessionHasErrors('autorizacion_referencia');
        $this->assertSame(0, Evento::count());

        $this->post(route('admin.eventos.store'), $this->payload(['licencia' => 'cc-by-nc', 'autorizacion_referencia' => 'Oficio 123-FACAGEI-2026']))
            ->assertSessionHasNoErrors();
        $event = Evento::firstOrFail();
        $this->assertSame('cc-by-nc', $event->licencia);

        $this->put(route('admin.eventos.update', $event), $this->payload(['licencia' => 'institucional']))->assertSessionHasNoErrors();
        $log = AuditLog::where('action', 'evento.actualizado')->latest('id')->firstOrFail();
        $this->assertSame(['antes' => 'cc-by-nc', 'despues' => 'institucional'], $log->details['cambios']['licencia']);
        // A partial update without the field keeps the reference.
        $this->assertSame('Oficio 123-FACAGEI-2026', $event->fresh()->autorizacion_referencia);

        $this->get(route('admin.eventos.show', $event))->assertOk()->assertSee('Oficio 123-FACAGEI-2026', false);
        auth()->logout();
        $this->get(route('eventos.show', $event))->assertOk()
            ->assertSee('Uso institucional · Todos los derechos reservados', false)
            ->assertDontSee('Oficio 123-FACAGEI-2026', false);
    }
}
