<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Categoria;
use App\Models\Evento;
use App\Models\InstitutionalAd;
use App\Models\User;
use App\Services\ChannelSchedule;
use App\Services\StorageCleanup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/** "Liberar espacio": qué se sugiere, qué se protege y que el borrado libera disco de verdad. */
class StorageCleanupTest extends TestCase
{
    use RefreshDatabase;

    private string $recordings;

    private User $admin;

    private Categoria $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->recordings = sys_get_temp_dir().'/uebtv-cleanup-test-'.Str::uuid();
        File::makeDirectory($this->recordings.'/vod', 0770, true);
        File::makeDirectory($this->recordings.'/raw', 0770, true);
        config(['streaming.recordings_root' => $this->recordings]);
        Storage::fake('local');
        Storage::fake('public');
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->category = Categoria::create(['nombre' => 'Académico', 'slug' => 'academico']);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->recordings);
        parent::tearDown();
    }

    private function event(string $title, array $attributes = [], int $age = 30): Evento
    {
        $event = Evento::create($attributes + [
            'titulo' => $title, 'slug' => Str::uuid(), 'categoria_id' => $this->category->id,
            'tipo_evento' => 'Conferencia', 'organizador_id' => $this->admin->id,
            'fecha_inicio' => now()->subDays($age), 'estado' => 'finalizado',
        ]);
        $event->forceFill(['created_at' => now()->subDays($age)])->saveQuietly();

        return $event;
    }

    /** Grabación lista: original FLV, MP4 y, si se pide, calidades. */
    private function recording(Evento $event, bool $renditions = true): array
    {
        $key = 'key_'.Str::random(8);
        $file = Str::uuid().'.mp4';
        file_put_contents($this->recordings.'/raw/'.$key.'.flv', str_repeat('r', 500));
        file_put_contents($this->recordings.'/vod/'.$file, str_repeat('m', 300));
        $dir = null;
        if ($renditions) {
            $dir = 'renditions/recording-'.$event->id.'-'.Str::random(12);
            Storage::disk('local')->put($dir.'/master.m3u8', str_repeat('x', 40));
            Storage::disk('local')->put($dir.'/720p/seg_00001.ts', str_repeat('x', 60));
        }
        $stream = $event->transmision()->create(['stream_key' => $key, 'recording_status' => 'ready', 'recording_file' => $file,
            'publicacion' => 'aprobada', 'renditions_status' => $renditions ? 'ready' : null, 'renditions_path' => $dir,
            'renditions_levels' => $renditions ? [['name' => '720p', 'width' => 1280, 'height' => 720]] : null]);

        return [$stream, $this->recordings.'/raw/'.$key.'.flv', $this->recordings.'/vod/'.$file, $dir];
    }

    private function keys(): array
    {
        return collect(app(StorageCleanup::class)->candidates())->pluck('key')->sort()->values()->all();
    }

    public function test_one_list_offers_everything_deletable_but_protects_what_is_in_use(): void
    {
        $old = $this->event('Clase antigua');
        $this->recording($old);
        $recent = $this->event('Clase de ayer', [], 1);
        $this->recording($recent, false);
        $live = $this->event('En directo', ['estado' => 'en_vivo']);
        $this->recording($live);
        $converting = $this->event('Convirtiéndose');
        $this->recording($converting, false);
        $converting->transmision->update(['recording_status' => 'processing']);
        $deleted = $this->event('Borrado antes');
        $this->recording($deleted, false);
        $deleted->delete();
        $upcoming = $this->event('Programa de mañana', ['fecha_inicio' => now()->addDay(), 'estado' => 'programado']);
        $upcoming->scheduledVideo()->create(['path' => 'scheduled-videos/next.mp4', 'processing_status' => 'ready', 'duration' => 60, 'ready_at' => now()->subDays(30)]);
        Storage::disk('local')->put('scheduled-videos/next.mp4', str_repeat('s', 200));
        $inactive = InstitutionalAd::create(['title' => 'Anuncio viejo', 'path' => 'institutional-ads/old.mp4', 'processing_status' => 'ready', 'duration' => 20, 'is_active' => false]);
        Storage::disk('local')->put('institutional-ads/old.mp4', str_repeat('a', 150));
        InstitutionalAd::create(['title' => 'Anuncio activo', 'path' => 'institutional-ads/on.mp4', 'processing_status' => 'ready', 'duration' => 20, 'is_active' => true]);
        Storage::disk('local')->put('institutional-ads/on.mp4', str_repeat('a', 150));

        $this->assertSame(collect(["ad:{$inactive->id}", "event:{$old->id}", "event:{$recent->id}", "event:{$deleted->id}"])->sort()->values()->all(), $this->keys());

        $items = collect(app(StorageCleanup::class)->candidates())->keyBy('key');
        // Everything related counts: OBS original, MP4 and qualities.
        $this->assertSame(900, $items["event:{$old->id}"]['bytes']);
        $this->assertContains('Pocas vistas', $items["event:{$old->id}"]['tags']);
        $this->assertContains('Ya eliminado', $items["event:{$deleted->id}"]['tags']);
        $this->assertContains('Desactivada', $items["ad:{$inactive->id}"]['tags']);
    }

    public function test_deleting_an_event_from_the_events_list_erases_it_with_all_its_files(): void
    {
        $event = $this->event('Clase antigua');
        [, $raw, $mp4, $dir] = $this->recording($event);
        $live = $this->event('En directo', ['estado' => 'en_vivo']);
        [, $liveRaw] = $this->recording($live, false);

        $this->actingAs($this->admin)->delete(route('admin.eventos.destroy', $event))
            ->assertRedirect(route('admin.eventos.index'))
            ->assertSessionHas('success', 'Evento eliminado con su video y archivos. Se liberaron 1 KB.');
        $this->assertNull(Evento::withTrashed()->find($event->id));
        $this->assertFileDoesNotExist($raw);
        $this->assertFileDoesNotExist($mp4);
        Storage::disk('local')->assertMissing($dir.'/master.m3u8');
        $this->assertSame(1, AuditLog::where('action', 'evento.eliminado')->count());

        // A live event cannot be deleted until the broadcast ends.
        $this->delete(route('admin.eventos.destroy', $live))->assertSessionHasErrors('evento');
        $this->assertFileExists($liveRaw);
        $this->assertNotNull($live->fresh());
    }

    public function test_deleting_content_removes_files_for_good_without_shifting_the_channel_schedule(): void
    {
        $event = $this->event('Clase antigua');
        [, $raw, $mp4, $dir] = $this->recording($event);

        // Two back-to-back programmes: the first already aired, the second is on air now.
        $aired = $this->event('Ya emitido', ['fecha_inicio' => now()->subHours(3), 'estado' => 'finalizado']);
        $aired->scheduledVideo()->create(['path' => 'scheduled-videos/aired.mp4', 'processing_status' => 'ready', 'duration' => 7200, 'ready_at' => now()->subDays(30)]);
        Storage::disk('local')->put('scheduled-videos/aired.mp4', str_repeat('s', 400));
        $onAir = $this->event('Al aire', ['fecha_inicio' => now()->subHours(3), 'estado' => 'programado']);
        $onAir->scheduledVideo()->create(['path' => 'scheduled-videos/now.mp4', 'processing_status' => 'ready', 'duration' => 7200, 'ready_at' => now()->subDays(30)]);
        Storage::disk('local')->put('scheduled-videos/now.mp4', str_repeat('s', 400));
        $before = app(ChannelSchedule::class)->timeline()[$onAir->id];
        $this->assertSame('playing', $before['state']);
        $this->assertNotContains("event:{$onAir->id}", $this->keys());

        $this->actingAs($this->admin)->post(route('admin.media.cleanup'), ['items' => ["event:{$event->id}", "event:{$aired->id}"]])
            ->assertSessionHas('success');

        $this->assertNull(Evento::withTrashed()->find($event->id));
        $this->assertFileDoesNotExist($raw);
        $this->assertFileDoesNotExist($mp4);
        Storage::disk('local')->assertMissing($dir.'/master.m3u8');
        Storage::disk('local')->assertMissing('scheduled-videos/aired.mp4');
        Storage::disk('local')->assertExists('scheduled-videos/now.mp4');
        $this->assertTrue($aired->fresh()->trashed());
        $after = app(ChannelSchedule::class)->timeline()[$onAir->id];
        $this->assertSame($before['starts_at'], $after['starts_at']);
        $this->assertSame('playing', $after['state']);
        $this->assertSame(2, AuditLog::where('action', 'evento.eliminado')->count());
        // Nothing left to offer for them.
        $this->assertNotContains("event:{$aired->id}", $this->keys());
    }

    public function test_items_in_use_or_invented_are_never_deleted_and_only_admins_can_free_space(): void
    {
        $live = $this->event('En directo', ['estado' => 'en_vivo']);
        [, $raw, $mp4] = $this->recording($live);

        $this->actingAs(User::factory()->create(['role' => 'operador']))
            ->post(route('admin.media.cleanup'), ['items' => ["event:{$live->id}"]])->assertForbidden();
        $this->actingAs($this->admin)->post(route('admin.media.cleanup'), ['items' => ["event:{$live->id}"]])
            ->assertSessionHasErrors('items');
        $this->post(route('admin.media.cleanup'), ['items' => ['../../etc/passwd']])->assertSessionHasErrors('items.0');

        $this->assertFileExists($raw);
        $this->assertFileExists($mp4);
        $this->assertNotNull($live->fresh());
    }

    public function test_media_library_opens_free_space_with_the_list(): void
    {
        $event = $this->event('Clase antigua');
        $this->recording($event);

        $this->actingAs($this->admin)->get(route('admin.media.index', ['liberar' => 1]))->assertOk()
            ->assertSee('&quot;open&quot;:true', false)->assertDontSee('event:'.$event->id, false);
        $this->getJson(route('admin.media.cleanup.list'))->assertOk()->assertJsonPath('items.0.key', 'event:'.$event->id)
            ->assertJsonPath('items.0.size', '1 KB');
        $this->actingAs(User::factory()->create(['role' => 'operador']))->getJson(route('admin.media.cleanup.list'))->assertForbidden();
        $this->actingAs($this->admin);
        $this->post(route('admin.media.cleanup'), ['items' => ['raw:1']])->assertSessionHasErrors('items.0');
    }

    public function test_admin_can_download_any_stored_video_even_from_a_deleted_event_before_freeing_space(): void
    {
        $recorded = $this->event('Clase grabada');
        [, , $mp4] = $this->recording($recorded, false);
        $programme = $this->event('Programa de la parrilla');
        $programme->scheduledVideo()->create(['path' => 'scheduled-videos/p.mp4', 'processing_status' => 'ready', 'duration' => 60, 'ready_at' => now()->subDays(30)]);
        Storage::disk('local')->put('scheduled-videos/p.mp4', 'programme-bytes');
        $programme->delete();
        $ad = InstitutionalAd::create(['title' => 'Anuncio', 'path' => 'institutional-ads/a.mp4', 'processing_status' => 'ready', 'duration' => 20, 'is_active' => false]);
        Storage::disk('local')->put('institutional-ads/a.mp4', 'ad-bytes');
        $empty = $this->event('Sin video', ['estado' => 'programado']);

        $this->actingAs(User::factory()->create(['role' => 'operador']))
            ->get(route('admin.media.download', $recorded->id))->assertForbidden();

        $this->actingAs($this->admin);
        $response = $this->get(route('admin.media.download', $recorded->id))->assertOk()->assertDownload();
        $this->assertSame($mp4, $response->baseResponse->getFile()->getPathname());
        $this->get(route('admin.media.download', $programme->id))->assertOk()->assertDownload();
        $this->get(route('admin.media.ad-download', $ad))->assertOk()->assertDownload('uebtv-publicidad-anuncio.mp4');
        $this->get(route('admin.media.download', $empty->id))->assertNotFound();
        $this->assertSame(3, AuditLog::where('action', 'video.descargado')->count());

        // The download link is offered in the events list and in "Liberar espacio".
        $items = collect(app(StorageCleanup::class)->candidates())->keyBy('key');
        $this->assertSame(route('admin.media.download', $programme->id), $items["event:{$programme->id}"]['downloadUrl']);
        $this->get(route('admin.eventos.index'))->assertSee(route('admin.media.download', $recorded->id), false);
    }
}
