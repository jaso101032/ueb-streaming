<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Evento;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminVideoAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private function video(string $title, User $admin): Evento
    {
        $category = Categoria::firstOrCreate(['slug' => 'general'], ['nombre' => 'General']);
        return Evento::create([
            'titulo' => $title,
            'slug' => str($title)->slug().'-'.uniqid(),
            'tipo_evento' => 'Conferencia',
            'categoria_id' => $category->id,
            'organizador_id' => $admin->id,
            'fecha_inicio' => now()->subDay(),
            'estado' => 'finalizado',
            'video_url' => 'https://example.org/video.mp4',
        ]);
    }

    private function visit(int $eventId, string $viewer, string $date): void
    {
        DB::table('event_video_views')->insert([
            'evento_id' => $eventId,
            'viewer_key' => $viewer,
            'viewed_on' => $date,
            'created_at' => now(),
        ]);
    }

    public function test_admin_gets_video_trend_and_catalog_rankings_for_selected_period(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $video = $this->video('Video actual', $admin);
        $popular = $this->video('Video popular', $admin);
        $this->visit($video->id, '11111111-1111-4111-8111-111111111111', now()->toDateString());
        $this->visit($video->id, '22222222-2222-4222-8222-222222222222', now()->toDateString());
        $this->visit($video->id, '33333333-3333-4333-8333-333333333333', now()->subDays(10)->toDateString());
        foreach (['44444444-4444-4444-8444-444444444444', '55555555-5555-4555-8555-555555555555', '66666666-6666-4666-8666-666666666666'] as $viewer) {
            $this->visit($popular->id, $viewer, now()->toDateString());
        }

        $response = $this->actingAs($admin)->getJson(route('admin.eventos.analytics', ['evento' => $video, 'days' => 7]));
        $response->assertOk()
            ->assertJsonPath('period', 7)
            ->assertJsonPath('current.views', 2)
            ->assertJsonPath('current.allTime', 3)
            ->assertJsonPath('current.peakDay.views', 2)
            ->assertJsonPath('mostViewed.0.title', 'Video popular')
            ->assertJsonPath('leastViewed.0.title', 'Video actual');
        $this->assertCount(7, $response->json('daily'));
    }

    public function test_video_analytics_are_admin_only_and_watch_props_do_not_expose_url_to_other_roles(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $video = $this->video('Video privado', $admin);

        $this->getJson(route('admin.eventos.analytics', $video))->assertUnauthorized();
        $operator = User::factory()->create(['role' => 'operador']);
        $this->actingAs($operator)->getJson(route('admin.eventos.analytics', $video))->assertForbidden();

        $this->get(route('eventos.show', $video))->assertOk()->assertDontSee('analyticsUrl');
        $this->actingAs($admin)->get(route('eventos.show', $video))->assertOk()->assertSee('analyticsUrl');
    }

    public function test_catalog_is_sorted_by_all_time_views_only_for_admins(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $older = $this->video('Video más antiguo', $admin);
        $popular = $this->video('Video más visto', $admin);
        $newer = $this->video('Video más nuevo', $admin);
        $older->update(['fecha_inicio' => now()->subDays(4)]);
        $popular->update(['fecha_inicio' => now()->subDays(3)]);
        $newer->update(['fecha_inicio' => now()->subDay()]);
        foreach (['11111111-1111-4111-8111-111111111111', '22222222-2222-4222-8222-222222222222', '33333333-3333-4333-8333-333333333333'] as $viewer) {
            $this->visit($popular->id, $viewer, now()->toDateString());
        }
        $this->visit($older->id, '44444444-4444-4444-8444-444444444444', now()->toDateString());

        $adminEvents = $this->actingAs($admin)->get(route('eventos.index'))->assertOk();
        $adminProps = $this->reactProps($adminEvents);
        $this->assertSame(['Video más visto', 'Video más antiguo', 'Video más nuevo'], array_column($adminProps['events'], 'title'));
        $this->assertSame(3, $adminProps['events'][0]['viewCount']);
        $this->assertTrue($adminProps['isAdmin']);

        $operator = User::factory()->create(['role' => 'operador']);
        $operatorProps = $this->reactProps($this->actingAs($operator)->get(route('eventos.index'))->assertOk());
        $this->assertSame(['Video más nuevo', 'Video más visto', 'Video más antiguo'], array_column($operatorProps['events'], 'title'));
        $this->assertFalse($operatorProps['isAdmin']);
        $this->assertArrayNotHasKey('viewCount', $operatorProps['events'][0]);
    }
}
