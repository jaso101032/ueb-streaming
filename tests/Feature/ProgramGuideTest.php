<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Evento;
use App\Models\User;
use App\Support\ProgramGuide;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Pestaña "Programación" de la página del video: al aire y a continuación, respetando la visibilidad. */
class ProgramGuideTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    private function event(string $title, array $attributes = []): Evento
    {
        $category = Categoria::firstOrCreate(['slug' => 'general'], ['nombre' => 'General']);

        return Evento::create($attributes + ['titulo' => $title, 'slug' => Str::slug($title).'-'.Str::random(4), 'tipo_evento' => 'Conferencia',
            'categoria_id' => $category->id, 'organizador_id' => $this->admin->id, 'fecha_inicio' => now()->addHour(), 'estado' => 'programado']);
    }

    private function program(string $title, $start, array $attributes = []): Evento
    {
        $event = $this->event($title, $attributes + ['fecha_inicio' => $start, 'tipo_evento' => 'Emisión pregrabada']);
        Storage::disk('local')->put("scheduled-videos/{$event->id}.mp4", 'mp4');
        $event->scheduledVideo()->create(['path' => "scheduled-videos/{$event->id}.mp4", 'duration' => 1800,
            'processing_status' => 'ready', 'ready_at' => now()->subDay()]);

        return $event;
    }

    public function test_the_guide_lists_what_is_on_air_first_and_then_what_comes_next(): void
    {
        $this->event('Graduación', ['fecha_inicio' => now()->addDays(2)]);
        $this->program('Documental en emisión', now()->subMinutes(5));
        $this->program('Clase magistral', now()->addHours(3));
        $this->event('Conferencia en vivo', ['estado' => 'en_vivo', 'fecha_inicio' => now()->subMinutes(20)]);
        $this->event('Evento pasado', ['estado' => 'finalizado', 'fecha_inicio' => now()->subDay()]);
        $this->event('Muy lejano', ['fecha_inicio' => now()->addDays(40)]);

        $guide = ProgramGuide::for(null);
        $this->assertSame(['Conferencia en vivo', 'Documental en emisión', 'Clase magistral', 'Graduación'], array_column($guide, 'title'));
        $this->assertSame(['now', 'now', 'next', 'next'], array_column($guide, 'when'));
        $this->assertSame(['live', 'channel', 'channel', 'live'], array_column($guide, 'kind'));
    }

    public function test_private_events_are_only_listed_for_people_who_can_watch_them(): void
    {
        $this->event('Abierto', ['fecha_inicio' => now()->addHour()]);
        $this->event('Solo comunidad', ['fecha_inicio' => now()->addHours(2), 'visibilidad' => 'interno']);
        $this->event('Solo personal', ['fecha_inicio' => now()->addHours(3), 'visibilidad' => 'restringido']);
        $this->program('Programa interno', now()->addHours(4), ['visibilidad' => 'interno']);

        $this->assertSame(['Abierto'], array_column(ProgramGuide::for(null), 'title'));
        $student = User::factory()->create(['role' => 'usuario']);
        $this->assertSame(['Abierto', 'Solo comunidad', 'Programa interno'], array_column(ProgramGuide::for($student), 'title'));
        $this->assertCount(4, ProgramGuide::for($this->admin));

        // The watch page receives the guide for the current visitor.
        $page = $this->event('Página', ['estado' => 'finalizado', 'fecha_inicio' => now()->subDay()]);
        $this->get(route('eventos.show', $page))->assertOk()->assertSee('Abierto')->assertDontSee('Solo personal');
    }

    public function test_more_to_watch_puts_live_and_upcoming_events_before_older_ones(): void
    {
        $current = $this->event('Actual', ['estado' => 'finalizado', 'fecha_inicio' => now()->subDays(3)]);
        $this->event('Antiguo', ['estado' => 'finalizado', 'fecha_inicio' => now()->subDays(30)]);
        $this->event('Próximo', ['fecha_inicio' => now()->addDay()]);
        $this->event('En vivo ahora', ['estado' => 'en_vivo', 'fecha_inicio' => now()->subHour()]);
        $this->event('Cancelado', ['estado' => 'cancelado', 'fecha_inicio' => now()->addDay()]);

        $html = $this->get(route('eventos.show', $current))->assertOk()->getContent();
        $live = strpos($html, 'En vivo ahora'); $next = strpos($html, 'Próximo'); $old = strpos($html, 'Antiguo');
        $this->assertTrue($live < $next && $next < $old, 'Live, then upcoming, then older events.');
        $this->assertStringNotContainsString('Cancelado', $html);
    }
}
