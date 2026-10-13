<?php

use App\Models\Carrera;
use App\Models\Categoria;
use App\Models\ChatMessage;
use App\Models\Evento;
use App\Models\Facultad;
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
$category = Categoria::create(['nombre' => 'Académico', 'slug' => 'academico']);
$faculty = Facultad::create(['nombre' => 'Ingeniería', 'slug' => 'ingenieria']);
Carrera::create(['nombre' => 'Software', 'slug' => 'software', 'facultad_id' => $faculty->id]);
$event = Evento::create(['titulo' => 'Conferencia inaugural UEB 2026', 'slug' => 'conferencia', 'estado' => 'finalizado', 'categoria_id' => $category->id, 'facultad_id' => $faculty->id, 'organizador_id' => $admin->id, 'tipo_evento' => 'Conferencias', 'fecha_inicio' => now()->subDay(), 'descripcion' => 'Ideas que se convierten en conocimiento. Un encuentro para compartir experiencias con nuestra comunidad.', 'video_url' => '/test-video.mp4']);
ChatMessage::create(['evento_id' => $event->id, 'user_id' => $admin->id, 'message' => 'Bienvenidos a nuestra comunidad.']);
// The archived event above deliberately has closed chat. Keep a separate
// upcoming room to exercise sending and moderation in browser tests.
$chatEvent = Evento::create(['titulo' => 'Encuentro de la comunidad', 'slug' => 'encuentro-comunidad',
    'estado' => 'programado', 'categoria_id' => $category->id, 'facultad_id' => $faculty->id,
    'organizador_id' => $admin->id, 'tipo_evento' => 'Conferencias',
    'fecha_inicio' => now()->addDay()]);
