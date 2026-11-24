<?php

use App\Models\Carrera;
use App\Models\Categoria;
use App\Models\ChatMessage;
use App\Models\Evento;
use App\Models\Facultad;
use App\Models\InstitutionalAd;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;

// A disposable database only. Never seed a real installation from browser tests.
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (config('database.default') !== 'sqlite' || ! str_contains(config('database.connections.sqlite.database'), 'uebtv-react-e2e')) {
    throw new RuntimeException('Use the isolated browser test database.');
}
$admin = User::firstOrCreate(['email' => 'admin@ui.test'], ['name' => 'Javier Administrador', 'email' => 'admin@ui.test', 'password' => 'Browser123!', 'role' => 'admin']);
User::firstOrCreate(['email' => 'student@ui.test'], ['name' => 'Estudiante Regular', 'email' => 'student@ui.test', 'password' => 'Browser123!', 'role' => 'estudiante']);
// Eventos de prueba con autorización de difusión vigente (sin ella, el público no los ve).
Evento::creating(function (Evento $e) {
    if (array_key_exists('autorizacion_fecha', $e->getAttributes())) return;
    $e->autorizacion_responsable ??= 'Rectorado';
    $e->autorizacion_referencia ??= 'Oficio de prueba';
    $e->autorizacion_fecha = now()->subDay()->toDateString();
});
$category = Categoria::create(['nombre' => 'Académico', 'slug' => 'academico']);
$faculty = Facultad::create(['nombre' => 'Ingeniería', 'slug' => 'ingenieria']);
Carrera::create(['nombre' => 'Software', 'slug' => 'software', 'facultad_id' => $faculty->id]);
$event = Evento::create(['titulo' => 'Conferencia inaugural UEB 2026', 'slug' => 'conferencia', 'estado' => 'finalizado', 'categoria_id' => $category->id, 'facultad_id' => $faculty->id, 'organizador_id' => $admin->id, 'tipo_evento' => 'Conferencias', 'fecha_inicio' => now()->subDay(), 'descripcion' => 'Ideas que se convierten en conocimiento. Un encuentro para compartir experiencias con nuestra comunidad.', 'video_url' => '/test-video.mp4']);
ChatMessage::create(['evento_id' => $event->id, 'user_id' => $admin->id, 'message' => 'Bienvenidos a nuestra comunidad.']);
ChatMessage::create(['evento_id' => $event->id, 'user_id' => $admin->id, 'message' => 'Comentario del minuto cinco.', 'playback_seconds' => 5]);
ChatMessage::create(['evento_id' => $event->id, 'user_id' => $admin->id, 'message' => 'Comentario del minuto cuarenta.', 'playback_seconds' => 40]);
// The archived event above deliberately has closed chat. Keep a separate
// upcoming room to exercise sending and moderation in browser tests.
$chatEvent = Evento::create(['titulo' => 'Encuentro de la comunidad', 'slug' => 'encuentro-comunidad',
    'estado' => 'programado', 'categoria_id' => $category->id, 'facultad_id' => $faculty->id,
    'organizador_id' => $admin->id, 'tipo_evento' => 'Conferencias',
    'fecha_inicio' => now()->addDay()]);
// Inactive ad (never airs, so it cannot interfere with the channel tests) used
// to check the advertising list layout with its subtitles block.
InstitutionalAd::create(['title' => 'UEB music', 'path' => 'institutional-ads/ueb-music.mp4', 'duration' => 175,
    'processing_status' => 'ready', 'ready_at' => now(), 'is_active' => false]);
// "Liberar espacio": an old disabled ad and a deleted programme whose files
// still use disk. Files live only in the isolated browser storage folder.
$disk = Illuminate\Support\Facades\Storage::disk('local');
$disk->put('institutional-ads/old-campaign.mp4', str_repeat('a', 3 * 1024 * 1024));
$oldAd = InstitutionalAd::create(['title' => 'Campaña de matrículas 2025', 'path' => 'institutional-ads/old-campaign.mp4', 'duration' => 30,
    'processing_status' => 'ready', 'ready_at' => now()->subDays(60), 'is_active' => false]);
$oldAd->forceFill(['created_at' => now()->subDays(60)])->saveQuietly();
$disk->put('scheduled-videos/old-programme.mp4', str_repeat('s', 5 * 1024 * 1024));
$oldProgramme = Evento::create(['titulo' => 'Programa retirado de prueba', 'slug' => 'programa-retirado', 'estado' => 'finalizado',
    'categoria_id' => $category->id, 'organizador_id' => $admin->id, 'tipo_evento' => 'Conferencias', 'fecha_inicio' => now()->subDays(40)]);
$oldProgramme->scheduledVideo()->create(['path' => 'scheduled-videos/old-programme.mp4', 'processing_status' => 'ready', 'duration' => 30, 'ready_at' => now()->subDays(41)]);
$oldProgramme->forceFill(['created_at' => now()->subDays(41)])->saveQuietly();
$oldProgramme->delete();
