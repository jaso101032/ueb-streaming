<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\ChatMessage;
use App\Models\Evento;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Criterios de aceptación de la tesis que no estaban cubiertos por otras
 * pruebas automatizadas (CP-HU-06-03, CP-HU-08-02, CP-HU-08-03, CP-HU-10-03).
 */
class AcceptanceCriteriaTest extends TestCase
{
    use RefreshDatabase;

    /** CP-HU-06-03: un rol enviado en el registro no concede privilegios; el correo duplicado se rechaza. */
    public function test_registration_ignores_a_manipulated_role_and_rejects_duplicate_email(): void
    {
        $this->post('/register', ['name' => 'Estudiante', 'email' => 'estudiante@ueb.edu.ec', 'role' => 'admin',
            'operator_permissions' => ['events'], 'password' => 'Segura123!', 'password_confirmation' => 'Segura123!']);
        $user = User::where('email', 'estudiante@ueb.edu.ec')->firstOrFail();
        $this->assertNotSame('admin', $user->role);
        $this->assertFalse($user->isAdmin());
        $this->assertNull($user->operator_permissions);
        $this->get(route('admin.usuarios.index'))->assertForbidden();

        auth()->logout();
        $this->post('/register', ['name' => 'Otra', 'email' => 'estudiante@ueb.edu.ec',
            'password' => 'Segura123!', 'password_confirmation' => 'Segura123!'])->assertSessionHasErrors('email');
        $this->assertSame(1, User::where('email', 'estudiante@ueb.edu.ec')->count());
    }

    /** CP-HU-08-02 y CP-HU-08-03: el perfil solo cambia campos propios y válidos. */
    public function test_profile_cannot_change_role_or_another_account_and_keeps_valid_data(): void
    {
        $user = User::factory()->create(['role' => 'usuario', 'email' => 'propio@ueb.edu.ec']);
        $other = User::factory()->create(['email' => 'otro@ueb.edu.ec', 'name' => 'Otra cuenta']);

        $this->actingAs($user)->patch('/profile', ['name' => 'Nuevo nombre', 'email' => 'propio@ueb.edu.ec',
            'role' => 'admin', 'id' => $other->id, 'operator_permissions' => ['events']])->assertSessionHasNoErrors();
        $user->refresh();
        $this->assertSame('Nuevo nombre', $user->name);
        $this->assertSame('usuario', $user->role);
        $this->assertNull($user->operator_permissions);
        $this->assertSame('Otra cuenta', $other->fresh()->name);

        $this->patch('/profile', ['name' => 'Cambio inválido', 'email' => 'otro@ueb.edu.ec'])->assertSessionHasErrors('email');
        $this->patch('/profile', ['name' => 'Cambio inválido', 'email' => 'no-es-correo'])->assertSessionHasErrors('email');
        $this->assertSame('Nuevo nombre', $user->fresh()->name);
        $this->assertSame('propio@ueb.edu.ec', $user->fresh()->email);
    }

    /** CP-HU-10-03: la moderación deja constancia de responsable, fecha, evento y mensaje sin exponerla al público. */
    public function test_moderation_is_recorded_in_the_audit_log_and_not_shown_publicly(): void
    {
        $moderator = User::factory()->create(['role' => 'operador', 'operator_permissions' => ['moderate_chat']]);
        $author = User::factory()->create(['name' => 'Autor del mensaje']);
        $category = Categoria::create(['nombre' => 'General', 'slug' => 'general']);
        $event = Evento::create(['titulo' => 'Foro', 'slug' => 'foro', 'tipo_evento' => 'Foro', 'categoria_id' => $category->id,
            'organizador_id' => $moderator->id, 'fecha_inicio' => now(), 'estado' => 'en_vivo']);
        $message = ChatMessage::create(['evento_id' => $event->id, 'user_id' => $author->id, 'message' => 'Mensaje a moderar']);

        $this->actingAs($moderator)->deleteJson(route('eventos.chat.destroy', [$event, $message]))->assertSuccessful();
        $log = \App\Models\AuditLog::where('action', 'chat.mensaje_moderado')->firstOrFail();
        $this->assertSame($moderator->id, $log->user_id);
        $this->assertSame($event->id, $log->subject_id);
        $this->assertSame($message->id, $log->details['mensaje_id']);
        $this->assertNotNull($log->created_at);

        auth()->logout();
        $this->get(route('eventos.show', $event))->assertOk()->assertDontSee('chat.mensaje_moderado')->assertDontSee($moderator->email);
        $this->get(route('admin.audit.index'))->assertRedirect();
    }
}
