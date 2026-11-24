<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Evento;
use App\Models\Transmision;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Ningún directo ni video se muestra al público sin una autorización de
 * difusión vigente registrada; la licencia de uso no la sustituye.
 */
class PublicationAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function event(array $authorization, array $extra = []): Evento
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $category = Categoria::create(['nombre' => 'Académico '.uniqid(), 'slug' => 'academico-'.uniqid()]);

        return Evento::create(array_merge(['titulo' => 'Defensa de titulación', 'slug' => 'defensa-'.uniqid(), 'estado' => 'programado',
            'categoria_id' => $category->id, 'organizador_id' => $admin->id, 'tipo_evento' => 'Conferencias', 'fecha_inicio' => now()->addHour(),
            'visibilidad' => 'publico'], $authorization, $extra));
    }

    private function none(): array
    {
        return ['autorizacion_responsable' => null, 'autorizacion_referencia' => null, 'autorizacion_fecha' => null, 'autorizacion_vigencia' => null];
    }

    public function test_event_without_authorization_is_hidden_from_public_but_visible_to_staff(): void
    {
        $event = $this->event($this->none());

        $this->assertFalse($event->hasValidAuthorization());
        $this->get(route('eventos.show', $event))->assertNotFound();
        $this->assertSame(0, Evento::visibleTo(null)->count());
        $this->actingAs(User::factory()->create(['role' => 'usuario']));
        $this->get(route('eventos.show', $event))->assertNotFound();
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->get(route('eventos.show', $event))->assertOk();
    }

    public function test_license_alone_does_not_authorize_broadcast(): void
    {
        $event = $this->event(['autorizacion_responsable' => null, 'autorizacion_referencia' => 'Licencia CC BY', 'autorizacion_fecha' => null], ['licencia' => 'cc-by']);

        $this->get(route('eventos.show', $event))->assertNotFound();
    }

    public function test_expired_or_future_authorization_does_not_publish(): void
    {
        $expired = $this->event(['autorizacion_responsable' => 'Rectorado', 'autorizacion_referencia' => 'Oficio 1',
            'autorizacion_fecha' => now()->subMonth()->toDateString(), 'autorizacion_vigencia' => now()->subDay()->toDateString()]);
        $future = $this->event(['autorizacion_responsable' => 'Rectorado', 'autorizacion_referencia' => 'Oficio 2',
            'autorizacion_fecha' => now()->addDay()->toDateString()]);
        $valid = $this->event(['autorizacion_responsable' => 'Rectorado', 'autorizacion_referencia' => 'Oficio 3',
            'autorizacion_fecha' => now()->toDateString(), 'autorizacion_vigencia' => now()->addMonth()->toDateString()]);

        $this->assertSame('vencida', $expired->authorizationStatus());
        $this->assertSame('futura', $future->authorizationStatus());
        $this->assertSame('vigente', $valid->authorizationStatus());
        $this->get(route('eventos.show', $expired))->assertNotFound();
        $this->get(route('eventos.show', $future))->assertNotFound();
        $this->get(route('eventos.show', $valid))->assertOk();
        $this->assertSame([$valid->id], Evento::visibleTo(null)->pluck('id')->all());
    }

    public function test_recording_cannot_be_approved_without_valid_authorization(): void
    {
        $event = $this->event($this->none(), ['estado' => 'finalizado']);
        $stream = Transmision::create(['evento_id' => $event->id, 'stream_key' => 'ueb_'.str_repeat('a', 32), 'publicacion' => 'pendiente']);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post(route('admin.recording.approve', $event))->assertSessionHasErrors('publicacion');
        $this->assertSame('pendiente', $stream->fresh()->publicacion);
    }

    public function test_admin_registers_and_revokes_authorization_with_audit(): void
    {
        $event = $this->event($this->none());
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->put(route('admin.authorization.update', $event), [
            'autorizacion_responsable' => 'Rectorado, Universidad Estatal de Bolívar', 'autorizacion_referencia' => 'Oficio UEB-R-2026-001',
            'autorizacion_alcance' => 'Directo y grabación, imagen y voz de los participantes', 'autorizacion_fecha' => now()->toDateString(),
        ])->assertSessionHasNoErrors();
        $this->assertTrue($event->fresh()->hasValidAuthorization());
        $this->assertDatabaseHas('audit_logs', ['action' => 'evento.autorizacion', 'subject_id' => $event->id]);
        auth()->logout();
        $this->get(route('eventos.show', $event))->assertOk();

        $this->actingAs($admin)->delete(route('admin.authorization.destroy', $event))->assertRedirect();
        $this->assertFalse($event->fresh()->hasValidAuthorization());
        $this->assertDatabaseHas('audit_logs', ['action' => 'evento.autorizacion_revocada', 'subject_id' => $event->id]);
        auth()->logout();
        $this->get(route('eventos.show', $event))->assertNotFound();
    }

    public function test_authorization_requires_responsible_document_and_past_date(): void
    {
        $event = $this->event($this->none());
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->put(route('admin.authorization.update', $event), ['autorizacion_fecha' => now()->addDay()->toDateString()])
            ->assertSessionHasErrors(['autorizacion_responsable', 'autorizacion_referencia', 'autorizacion_fecha']);
        $this->assertFalse($event->fresh()->hasValidAuthorization());
    }

    public function test_operator_cannot_register_authorization(): void
    {
        $event = $this->event($this->none());
        $operator = User::factory()->create(['role' => 'operador']);

        $this->actingAs($operator)->put(route('admin.authorization.update', $event), [
            'autorizacion_responsable' => 'X', 'autorizacion_referencia' => 'Y', 'autorizacion_fecha' => now()->toDateString(),
        ])->assertForbidden();
    }
}
