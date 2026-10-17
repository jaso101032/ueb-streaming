<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Evento;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_admins_can_open_user_management_and_only_admins_get_the_link(): void
    {
        $this->get(route('admin.usuarios.index'))->assertRedirect(route('login'));
        foreach (['usuario', 'operador'] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->actingAs($user)->get(route('admin.usuarios.index'))->assertForbidden();
            $response = $this->get('/senal')->assertOk();
            preg_match('/data-shell="([^"]*)"/', $response->getContent(), $match);
            $shell = json_decode(html_entity_decode($match[1], ENT_QUOTES), true);
            $this->assertNotContains('Gestionar usuarios', array_column($shell['links'], 'label'));
        }

        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get(route('admin.usuarios.index'))->assertOk()->assertSee('data-react-page="UserManagement"', false);
        $response = $this->get('/senal')->assertOk();
        preg_match('/data-shell="([^"]*)"/', $response->getContent(), $match);
        $shell = json_decode(html_entity_decode($match[1], ENT_QUOTES), true);
        $this->assertContains('Gestionar usuarios', array_column($shell['links'], 'label'));
    }

    public function test_legacy_user_role_is_presented_as_usuario(): void
    {
        User::factory()->create(['role' => 'user']);
        $admin = User::factory()->create(['role' => 'admin']);
        $props = $this->reactProps($this->actingAs($admin)->get(route('admin.usuarios.index')));

        $this->assertSame('usuario', collect($props['users'])->firstWhere('email', User::where('role', 'user')->value('email'))['role']);
    }

    public function test_admin_can_create_update_and_delete_accounts(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);

        $this->post(route('admin.usuarios.store'), [
            'name' => 'Operador de prueba', 'email' => 'operador@ueb.edu.ec', 'role' => 'operador',
            'password' => 'SecurePass123!', 'password_confirmation' => 'SecurePass123!',
        ])->assertRedirect(route('admin.usuarios.index'))->assertSessionHasNoErrors();

        $created = User::where('email', 'operador@ueb.edu.ec')->firstOrFail();
        $this->assertSame('operador', $created->role);
        $this->assertNotNull($created->email_verified_at);
        $this->assertTrue(Hash::check('SecurePass123!', $created->password));

        $this->put(route('admin.usuarios.update', $created), [
            'name' => 'Operador actualizado', 'email' => 'operador@ueb.edu.ec', 'role' => 'usuario',
            'password' => '', 'password_confirmation' => '',
        ])->assertRedirect(route('admin.usuarios.index'))->assertSessionHasNoErrors();
        $this->assertSame('usuario', $created->fresh()->role);
        $this->delete(route('admin.usuarios.destroy', $created))->assertRedirect(route('admin.usuarios.index'));
        $this->assertDatabaseMissing('users', ['id' => $created->id]);
    }

    public function test_user_validation_and_account_safety_rules_are_enforced(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);
        $this->post(route('admin.usuarios.store'), [
            'name' => 'Cuenta', 'email' => 'bad-role@example.com', 'role' => 'superadmin',
            'password' => 'SecurePass123!', 'password_confirmation' => 'SecurePass123!',
        ])->assertSessionHasErrors('role');

        $this->put(route('admin.usuarios.update', $admin), [
            'name' => $admin->name, 'email' => $admin->email, 'role' => 'usuario',
        ])->assertSessionHasErrors('role');
        $this->delete(route('admin.usuarios.destroy', $admin))->assertSessionHasErrors('user');

        $category = Categoria::create(['nombre' => 'Institucional', 'slug' => 'institucional']);
        $owner = User::factory()->create(['role' => 'usuario']);
        $event = Evento::create([
            'titulo' => 'Evento protegido', 'slug' => 'evento-protegido', 'tipo_evento' => 'Conferencia',
            'fecha_inicio' => now(), 'organizador_id' => $owner->id, 'categoria_id' => $category->id,
        ]);
        $this->delete(route('admin.usuarios.destroy', $owner))->assertSessionHasErrors('user');
        $this->assertDatabaseHas('eventos', ['id' => $event->id, 'organizador_id' => $owner->id]);
    }
}
