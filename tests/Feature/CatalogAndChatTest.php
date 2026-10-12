<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\ChatMessage;
use App\Models\Evento;
use App\Models\Facultad;
use App\Models\User;
use App\Models\Transmision;
use App\Services\ChatFeed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class CatalogAndChatTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Categoria $categoria;

    private Facultad $facultad;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->categoria = Categoria::create(['nombre' => 'Conferencias', 'slug' => 'conferencias']);
        $this->facultad = Facultad::create(['nombre' => 'Ingeniería', 'slug' => 'ingenieria']);
    }

    private function evento(array $data = []): Evento
    {
        return Evento::create(array_merge([
            'titulo' => 'Ciencia abierta', 'slug' => (string) Str::uuid(),
            'tipo_evento' => 'Conferencias', 'categoria_id' => $this->categoria->id,
            'facultad_id' => $this->facultad->id, 'organizador_id' => $this->user->id,
            'fecha_inicio' => now()->addDay(), 'estado' => 'programado',
        ], $data));
    }

    private function message(Evento $evento, string $text = 'Hola'): ChatMessage
    {
        return ChatMessage::create(['evento_id' => $evento->id, 'user_id' => $this->user->id, 'message' => $text]);
    }

    public function test_catalog_combines_search_faculty_category_and_state(): void
    {
        $match = $this->evento(['estado' => 'en_vivo']);
        $this->evento(['estado' => 'en_vivo', 'facultad_id' => null]);
        $otherCategory = Categoria::create(['nombre' => 'Cultura', 'slug' => 'cultura']);
        $this->evento(['estado' => 'en_vivo', 'categoria_id' => $otherCategory->id]);
        $this->evento(['titulo' => 'Otro título', 'estado' => 'en_vivo']);
        $this->evento(['estado' => 'cancelado']);
        $this->evento()->delete();
        $this->get(route('eventos.index', ['q' => 'CIENCIA', 'facultad_id' => $this->facultad->id, 'categoria_id' => $this->categoria->id, 'filtro' => 'en_vivo']))
            ->assertOk()->assertViewHas('eventos', fn ($events) => $events->pluck('id')->all() === [$match->id]);
    }

    public function test_search_treats_wildcards_as_literal_text(): void
    {
        $match = $this->evento(['titulo' => 'Avance 100%_real!']);
        $this->evento(['titulo' => 'Avance 100Xreal']);
        $this->get(route('eventos.index', ['q' => '%_real!']))->assertOk()
            ->assertViewHas('eventos', fn ($events) => $events->pluck('id')->all() === [$match->id]);
    }

    public function test_upcoming_is_future_scheduled_and_sorted_nearest_first(): void
    {
        $later = $this->evento(['fecha_inicio' => now()->addDays(4)]);
        $sooner = $this->evento(['fecha_inicio' => now()->addHour()]);
        $this->evento(['fecha_inicio' => now()->subDay()]);
        $this->evento(['estado' => 'cancelado']);
        $this->evento(['estado' => 'finalizado']);
        $this->get(route('eventos.index'))->assertOk()
            ->assertViewHas('proximos', fn ($events) => $events->pluck('id')->all() === [$sooner->id, $later->id]);
        $this->get(route('eventos.index', ['filtro' => 'proximos']))->assertOk()
            ->assertViewHas('eventos', fn ($events) => $events->pluck('id')->all() === [$sooner->id, $later->id]);
    }

    public function test_pagination_preserves_filters_and_empty_state_is_clear(): void
    {
        for ($i = 0; $i < 13; $i++) {
            $this->evento();
        }
        $this->get(route('eventos.index', ['q' => 'Ciencia', 'categoria_id' => $this->categoria->id]))
            ->assertOk()->assertViewHas('eventos', fn ($events) => $events->count() === 12 && str_contains($events->nextPageUrl(), 'q=Ciencia') && str_contains($events->nextPageUrl(), 'categoria_id='));
        $this->assertSame([], $this->reactProps($this->get(route('eventos.index', ['q' => 'inexistente'])))['events']);
        $this->getJson(route('eventos.index', ['q' => ['invalid']]))->assertUnprocessable();
    }

    public function test_chat_and_initial_room_are_bounded_to_latest_80_messages(): void
    {
        $evento = $this->evento();
        for ($i = 1; $i <= 83; $i++) {
            $this->message($evento, 'Mensaje '.$i);
        }
        $other = $this->evento();
        $this->message($other, 'Otra sala');
        $this->getJson(route('eventos.chat.index', $evento))->assertOk()->assertJsonCount(80)
            ->assertJsonPath('0.message', 'Mensaje 4')->assertJsonPath('79.message', 'Mensaje 83');
        $this->get(route('eventos.show', $evento))->assertOk()->assertViewHas('chatMessages', fn ($messages) => count($messages) === 80)
            ->assertSee('&quot;canModerate&quot;:false', false);
    }

    public function test_feed_reuses_cache_and_returns_304_when_unchanged(): void
    {
        $evento = $this->evento();
        $this->message($evento);
        $response = $this->getJson(route('eventos.chat.index', $evento))->assertOk();
        $this->getJson(route('eventos.chat.index', $evento), ['If-None-Match' => $response->headers->get('ETag')])->assertStatus(304)->assertContent('');
        DB::enableQueryLog();
        DB::flushQueryLog();
        app(ChatFeed::class)->messages($evento);
        $this->assertSame([], DB::getQueryLog());
        DB::disableQueryLog();
    }

    public function test_send_requires_auth_validates_text_and_invalidates_feed(): void
    {
        $evento = $this->evento();
        $this->postJson(route('eventos.chat.store', $evento), ['message' => 'Hola'])->assertUnauthorized();
        $before = $this->getJson(route('eventos.chat.index', $evento));
        $this->actingAs($this->user);
        foreach (['   ', str_repeat('a', 181)] as $invalid) {
            $this->postJson(route('eventos.chat.store', $evento), ['message' => $invalid])->assertUnprocessable()->assertJsonValidationErrors('message');
        }
        $this->postJson(route('eventos.chat.store', $evento), ['message' => ' Hola comunidad '])->assertCreated()->assertJsonPath('message', 'Hola comunidad');
        $this->getJson(route('eventos.chat.index', $evento), ['If-None-Match' => $before->headers->get('ETag')])->assertOk()->assertJsonCount(1);
    }

    public function test_live_chat_messages_store_and_expose_their_position_in_the_recording(): void
    {
        $startedAt = now()->subMinutes(3);
        $evento = $this->evento(['estado' => 'en_vivo']);
        Transmision::create([
            'evento_id' => $evento->id,
            'stream_key' => 'ueb_'.Str::random(32),
            'iniciado_en' => $startedAt,
            'activo' => true,
        ]);

        $this->travelTo($startedAt->copy()->addSeconds(95));
        $response = $this->actingAs($this->user)
            ->postJson(route('eventos.chat.store', $evento), ['message' => 'Gran evento'])
            ->assertCreated()
            ->assertJsonPath('playback_seconds', 95);

        $messageId = $response->json('id');
        $this->assertDatabaseHas('chat_messages', [
            'id' => $messageId,
            'playback_seconds' => 95,
        ]);
        $this->getJson(route('eventos.chat.index', $evento))
            ->assertOk()
            ->assertJsonPath('0.playback_seconds', 95);
    }

    public function test_messages_outside_an_active_live_stream_have_no_playback_position(): void
    {
        $evento = $this->evento();
        $this->actingAs($this->user)
            ->postJson(route('eventos.chat.store', $evento), ['message' => 'Hola'])
            ->assertCreated()
            ->assertJsonPath('playback_seconds', null);
    }

    public function test_chat_is_closed_after_a_live_stream_finishes_but_its_history_remains_available(): void
    {
        $evento = $this->evento(['estado' => 'finalizado']);
        $this->message($evento, 'Mensaje conservado');

        $this->actingAs($this->user)
            ->postJson(route('eventos.chat.store', $evento), ['message' => 'No debe enviarse'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('message');
        $this->getJson(route('eventos.chat.index', $evento))
            ->assertOk()
            ->assertJsonPath('0.message', 'Mensaje conservado');
    }

    public function test_only_moderators_can_remove_messages_and_deletion_propagates(): void
    {
        $evento = $this->evento();
        foreach (['admin', 'operador'] as $role) {
            $message = $this->message($evento);
            app(ChatFeed::class)->forget($evento);
            $before = $this->getJson(route('eventos.chat.index', $evento));
            $this->actingAs($this->user)->deleteJson(route('eventos.chat.destroy', [$evento, $message]))->assertForbidden();
            $moderator = User::factory()->create(['role' => $role]);
            $this->actingAs($moderator)->get(route('eventos.show', $evento))->assertOk()->assertSee('&quot;canModerate&quot;:true', false);
            $this->deleteJson(route('eventos.chat.destroy', [$evento, $message]))->assertNoContent();
            $this->assertSoftDeleted('chat_messages', ['id' => $message->id, 'moderated_by' => $moderator->id]);
            $this->getJson(route('eventos.chat.index', $evento), ['If-None-Match' => $before->headers->get('ETag')])->assertOk()->assertExactJson([]);
        }
    }

    public function test_moderation_cannot_target_another_events_message(): void
    {
        $evento = $this->evento();
        $message = $this->message($this->evento());
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->deleteJson(route('eventos.chat.destroy', [$evento, $message]))->assertNotFound();
        $this->assertNotSoftDeleted($message);
    }

    public function test_chat_escapes_markup_in_initial_render(): void
    {
        $evento = $this->evento();
        $this->message($evento, '<script>alert(1)</script>');
        $this->get(route('eventos.show', $evento))->assertOk()->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
    }
}
