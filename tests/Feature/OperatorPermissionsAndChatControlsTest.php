<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\ChatMessage;
use App\Models\Evento;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperatorPermissionsAndChatControlsTest extends TestCase
{
    use RefreshDatabase;

    private function event(User $owner): Evento
    {
        $category = Categoria::create(['nombre' => 'Institucional', 'slug' => 'institucional']);

        return Evento::create([
            'titulo' => 'Directo de prueba', 'slug' => 'directo-de-prueba',
            'tipo_evento' => 'Conferencia', 'categoria_id' => $category->id,
            'organizador_id' => $owner->id, 'fecha_inicio' => now()->addHour(),
            'estado' => 'programado',
        ]);
    }

    public function test_admin_assigns_individual_operator_rights_and_server_enforces_them(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->post(route('admin.usuarios.store'), [
            'name' => 'Operador limitado', 'email' => 'limitado@ueb.edu.ec', 'role' => 'operador',
            'password' => 'SecurePass123!', 'password_confirmation' => 'SecurePass123!',
            'permissions_present' => '1', 'permissions' => ['stream'],
        ])->assertSessionHasNoErrors();
        $operator = User::where('email', 'limitado@ueb.edu.ec')->firstOrFail();
        $this->assertSame(['stream'], $operator->operator_permissions);
        $event = $this->event($admin);

        $this->actingAs($operator)->get(route('streaming.control'))->assertOk();
        $this->get(route('admin.eventos.index'))->assertForbidden();
        $this->get(route('admin.eventos.create'))->assertForbidden();
        $this->get(route('admin.usuarios.index'))->assertForbidden();
        $this->get(route('admin.media.index'))->assertForbidden();
        $this->get(route('admin.recording.download', $event))->assertForbidden();
        $this->get('/dashboard')->assertRedirect(route('streaming.control'));

        $message = ChatMessage::create(['evento_id' => $event->id, 'user_id' => $admin->id, 'message' => 'Hola']);
        $this->deleteJson(route('eventos.chat.destroy', [$event, $message]))->assertForbidden();
        $this->post(route('streaming.chat.toggle', $event), ['enabled' => '0'])->assertForbidden();
        $this->get(route('eventos.show', $event))->assertOk()->assertSee('&quot;canModerate&quot;:false', false);

        $this->actingAs($admin)->put(route('admin.usuarios.update', $operator), [
            'name' => $operator->name, 'email' => $operator->email, 'role' => 'operador',
            'permissions_present' => '1', 'permissions' => ['events', 'moderate_chat'],
        ])->assertSessionHasNoErrors();
        $this->actingAs($operator->fresh())->get(route('streaming.control'))->assertForbidden();
        $this->get(route('admin.eventos.index'))->assertOk();
        $this->get('/dashboard')->assertRedirect(route('admin.eventos.index'));
        $this->deleteJson(route('eventos.chat.destroy', [$event, $message]))->assertNoContent();
    }

    public function test_operators_only_act_with_explicit_permissions_and_promoted_users_get_none(): void
    {
        // Existing operators received their historical rights explicitly in the
        // 2026-11-10 migration; an operator without a list gets no implicit rights.
        $assigned = User::factory()->create(['role' => 'operador', 'operator_permissions' => User::operatorActions()]);
        $this->actingAs($assigned)->get(route('streaming.control'))->assertOk();
        $this->get(route('admin.eventos.index'))->assertOk();
        $unassigned = User::factory()->create(['role' => 'operador']);
        $this->assertNull($unassigned->operator_permissions);
        $this->actingAs($unassigned)->get(route('streaming.control'))->assertForbidden();
        $this->get(route('admin.eventos.index'))->assertForbidden();
        $this->post(route('streaming.generar.clave', $this->event($assigned)))->assertForbidden();

        $admin = User::factory()->create(['role' => 'admin']);
        $regular = User::factory()->create(['role' => 'usuario']);
        $this->actingAs($admin)->put(route('admin.usuarios.update', $regular), [
            'name' => $regular->name, 'email' => $regular->email, 'role' => 'operador',
        ])->assertSessionHasNoErrors();
        $this->assertSame([], $regular->fresh()->operator_permissions);
        $this->actingAs($regular->fresh())->get(route('streaming.control'))->assertForbidden();
        $this->get('/dashboard')->assertRedirect(route('channel.index'));
    }

    public function test_chat_only_operator_can_use_room_control_without_event_or_stream_access(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $operator = User::factory()->create(['role' => 'operador', 'operator_permissions' => ['toggle_chat']]);
        $event = $this->event($admin);

        $this->actingAs($operator)->get(route('admin.eventos.show', $event))->assertForbidden();
        $this->get(route('streaming.control'))->assertForbidden();
        $this->get(route('eventos.show', $event))->assertOk()
            ->assertSee('&quot;chatToggleUrl&quot;:', false)
            ->assertSee(route('streaming.chat.toggle', $event), false);
        $this->post(route('streaming.chat.toggle', $event), ['enabled' => '0'])->assertSessionHasNoErrors();
        $this->assertFalse($event->fresh()->chat_enabled);
    }

    public function test_admin_can_toggle_chat_during_live_and_restrict_one_user_without_losing_history(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $viewer = User::factory()->create(['role' => 'usuario']);
        $event = $this->event($admin);
        $event->update(['estado' => 'en_vivo']);

        $this->actingAs($viewer)->postJson(route('eventos.chat.store', $event), ['message' => 'Antes del cierre'])->assertCreated();
        $this->actingAs($admin)->post(route('streaming.chat.toggle', $event), ['enabled' => '0'])->assertSessionHasNoErrors();
        $this->assertFalse($event->fresh()->chat_enabled);
        $this->actingAs($viewer)->postJson(route('eventos.chat.store', $event), ['message' => 'Bloqueado'])->assertUnprocessable()->assertJsonValidationErrors('message');
        $this->getJson(route('eventos.chat.index', $event))->assertHeader('X-Chat-Enabled', '0')->assertJsonCount(1);

        $this->actingAs($admin)->put(route('admin.usuarios.update', $viewer), [
            'name' => $viewer->name, 'email' => $viewer->email, 'role' => 'usuario', 'chat_restricted' => '1',
        ])->assertSessionHasNoErrors();
        $this->post(route('streaming.chat.toggle', $event), ['enabled' => '1'])->assertSessionHasNoErrors();
        $this->actingAs($viewer->fresh())->getJson(route('eventos.chat.index', $event))->assertHeader('X-Chat-Restricted', '1')->assertJsonCount(1);
        $this->postJson(route('eventos.chat.store', $event), ['message' => 'Restringido'])->assertUnprocessable()->assertJsonValidationErrors('message');
        $this->actingAs($admin)->postJson(route('eventos.chat.store', $event), ['message' => 'Administrador'])->assertCreated();
    }
}
