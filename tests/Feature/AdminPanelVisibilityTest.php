<?php
namespace Tests\Feature;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class AdminPanelVisibilityTest extends TestCase {
    use RefreshDatabase;
    public function test_only_admin_receives_management_navigation_and_access(): void {
        foreach ([null, 'usuario', 'estudiante', 'operador', 'admin'] as $role) {
            auth()->logout();
            if ($role) $this->actingAs(User::factory()->create(['role' => $role]));
            $response = $this->get('/senal')->assertOk();
            preg_match('/data-shell="([^"]*)"/', $response->getContent(), $match);
            $shell = json_decode(html_entity_decode($match[1], ENT_QUOTES), true);
            $this->assertSame($role === 'admin', $shell['operator']);
            if ($role !== 'admin') {
                $this->assertSame([], $shell['links']);
                $this->assertNull($shell['createUrl']);
            }
            foreach (['admin.dashboard', 'admin.eventos.index', 'streaming.control', 'admin.scheduled.index'] as $route) {
                $result = $this->get(route($route));
                if (!$role) $result->assertRedirect(route('login'));
                elseif ($role === 'admin') $result->assertOk();
                else $result->assertForbidden();
            }
        }
    }
}
