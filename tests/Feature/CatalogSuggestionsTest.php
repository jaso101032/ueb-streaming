<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Evento;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Sugerencias del buscador del catálogo. */
class CatalogSuggestionsTest extends TestCase
{
    use RefreshDatabase;

    private function event(string $title, array $attributes = []): Evento
    {
        $category = Categoria::firstOrCreate(['slug' => 'general'], ['nombre' => 'General']);

        return Evento::create($attributes + ['titulo' => $title, 'slug' => Str::slug($title).'-'.Str::random(4), 'tipo_evento' => 'Conferencia',
            'categoria_id' => $category->id, 'organizador_id' => User::factory()->create(['role' => 'admin'])->id,
            'fecha_inicio' => now()->subDay(), 'estado' => 'finalizado', 'visibilidad' => 'publico']);
    }

    private function titles(string $term): array
    {
        return array_column($this->getJson(route('eventos.suggestions', ['q' => $term]))->assertOk()->json(), 'title');
    }

    public function test_suggestions_ignore_accents_and_rank_titles_that_start_with_the_term_first(): void
    {
        $this->event('Taller de subtítulos');
        $this->event('Subtítulos automáticos', ['fecha_inicio' => now()->subWeek()]);
        $this->event('Concierto');

        $this->assertSame(['Subtítulos automáticos', 'Taller de subtítulos'], $this->titles('SUBTITULOS'));
        $this->assertSame([], $this->titles('s'), 'One letter is not enough to suggest.');
        $this->assertSame([], $this->titles('%'), 'Wildcards are taken literally.');
    }

    public function test_suggestions_only_show_events_the_person_can_watch(): void
    {
        $this->event('Clase pública');
        $this->event('Clase interna', ['visibilidad' => 'interno']);
        $this->event('Clase cancelada', ['estado' => 'cancelado']);
        $live = $this->event('Clase en vivo', ['estado' => 'en_vivo']);

        $this->assertEqualsCanonicalizing(['Clase pública', 'Clase en vivo'], $this->titles('clase'));
        $this->actingAs(User::factory()->create(['role' => 'estudiante']));
        $this->assertContains('Clase interna', $this->titles('clase'));
        $suggestion = collect($this->getJson(route('eventos.suggestions', ['q' => 'en vivo']))->json())->firstWhere('id', $live->id);
        $this->assertTrue($suggestion['live']);
        $this->assertSame('/eventos/'.$live->id, $suggestion['url']);
        $this->assertNull($suggestion['image'], 'Events without a thumbnail fall back to the UEB logo.');
        $this->assertSame(now()->format('d/m/Y'), $this->event('Clase con miniatura', ['miniatura' => 'eventos/miniaturas/a.png', 'fecha_inicio' => now()])->fecha_inicio->format('d/m/Y'));
        $withImage = collect($this->getJson(route('eventos.suggestions', ['q' => 'miniatura']))->json())->first();
        $this->assertStringEndsWith('/storage/eventos/miniaturas/a.png', $withImage['image']);
        $this->assertSame(now()->format('d/m/Y'), $withImage['date']);
    }
}
