<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_is_sent_to_the_admin_dashboard(): void
    {
        $user = User::factory()->create([
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertRedirectToRoute('admin.dashboard');
    }

    public function test_operator_is_sent_to_streaming_control(): void
    {
        $user = User::factory()->create([
            'role' => 'operador',
            'operator_permissions' => User::operatorActions(),
            'email_verified_at' => now(),
        ]);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertRedirectToRoute('streaming.control');
    }

    public function test_regular_user_is_sent_to_the_public_catalog(): void
    {
        $user = User::factory()->create([
            'role' => 'usuario',
            'email_verified_at' => now(),
        ]);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertRedirectToRoute('eventos.index');
    }

    public function test_regular_user_cannot_create_events(): void
    {
        $user = User::factory()->create(['role' => 'usuario']);

        $this->actingAs($user)
            ->get('/admin/eventos/create')
            ->assertForbidden();
    }
}
