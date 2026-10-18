<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\TransmisionController;
use App\Http\Controllers\EventoPublicoController;
use App\Http\Controllers\EventoController;
use App\Http\Controllers\ChatMessageController;
use App\Models\Evento;

// The public channel automatically follows the live signal and scheduled playlist.
Route::get('/', fn () => redirect()->route('channel.index'));
Route::get('/senal', [\App\Http\Controllers\ChannelController::class, 'index'])->name('channel.index');
Route::get('/senal/estado', [\App\Http\Controllers\ChannelController::class, 'state'])->name('channel.state');
Route::get('/senal/publicidad/{ad}/video', [\App\Http\Controllers\InstitutionalAdController::class, 'media'])->name('channel.ad.media');

Route::get('/eventos', [EventoPublicoController::class, 'index'])->name('eventos.index');
Route::get('/eventos/{evento}/reproduccion', [\App\Http\Controllers\RecordingController::class, 'state'])->name('eventos.playback-state');
Route::get('/eventos/{evento}/grabacion', [\App\Http\Controllers\RecordingController::class, 'show'])->name('eventos.recording');
// Presence is anonymous telemetry, not an account action. It must work from
// every public viewer (including phones behind the local HLS proxy), so it
// cannot rely on a browser session's CSRF token. Rate limiting remains active.
Route::post('/eventos/{evento}/espectadores', [TransmisionController::class, 'viewerHeartbeat'])
    ->middleware('throttle:30,1')
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class])
    ->name('eventos.viewers');
// A view is recorded only after the browser has actually played a video.
Route::post('/eventos/{evento}/visualizaciones', [\App\Http\Controllers\VideoViewController::class, 'store'])
    ->middleware('throttle:120,1')
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class])
    ->name('eventos.video-views');
Route::get('/eventos/{evento}', [EventoPublicoController::class, 'show'])->name('eventos.show');
Route::get('/eventos/{evento}/chat', [ChatMessageController::class, 'index'])->name('eventos.chat.index');
Route::get('/eventos/{evento}/chat/replay', [ChatMessageController::class, 'replay'])->name('eventos.chat.replay');
Route::post('/eventos/{evento}/chat', [ChatMessageController::class, 'store'])->middleware(['auth', 'throttle:30,1'])->name('eventos.chat.store');

Route::delete('/eventos/{evento}/chat/{message}', [ChatMessageController::class, 'destroy'])->middleware(['auth', 'role:admin,operador', 'operator.permission:moderate_chat', 'throttle:60,1'])->name('eventos.chat.destroy');

// 2. Página de inicio general post-login (Redirige según el rol exacto)
Route::get('/dashboard', function () {
    $user = Auth::user();
    $role = trim(strtolower($user->role ?? ''));
    
    if ($role === 'admin') {
        return redirect()->route('admin.dashboard');
    } 
    if ($role === 'operador') {
        return redirect()->route($user->allowsOperatorAction('stream') ? 'streaming.control' : ($user->allowsOperatorAction('events') ? 'admin.eventos.index' : 'channel.index'));
    }
    

    
    // Los usuarios regulares entran directamente a la programación pública.
    if (Evento::where('estado', 'en_vivo')->exists()) {
        return redirect()->route('eventos.index', ['filtro' => 'en_vivo']);
    }

    return redirect()->route('eventos.index');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Panel de Administrador / Operador para la Gestión de Eventos
// Funciones de gestión exclusivas del administrador.
Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/medios', [\App\Http\Controllers\Admin\MediaLibraryController::class, 'index'])->name('media.index');
    Route::get('/medios/programados/{evento}/video', [\App\Http\Controllers\ScheduledVideoController::class, 'adminPreview'])->name('media.scheduled-preview');
    Route::get('/medios/publicidad/{ad}/video', [\App\Http\Controllers\InstitutionalAdController::class, 'adminPreview'])->name('media.ad-preview');
    Route::get('/usuarios', [\App\Http\Controllers\Admin\UserController::class, 'index'])->name('usuarios.index');
    Route::get('/eventos/{evento}/estadisticas', [\App\Http\Controllers\Admin\VideoAnalyticsController::class, 'show'])->name('eventos.analytics');
    Route::post('/usuarios', [\App\Http\Controllers\Admin\UserController::class, 'store'])->middleware('throttle:10,1')->name('usuarios.store');
    Route::put('/usuarios/{usuario}', [\App\Http\Controllers\Admin\UserController::class, 'update'])->middleware('throttle:20,1')->name('usuarios.update');
    Route::delete('/usuarios/{usuario}', [\App\Http\Controllers\Admin\UserController::class, 'destroy'])->middleware('throttle:10,1')->name('usuarios.destroy');
    Route::get('/marca-de-agua', [\App\Http\Controllers\Admin\PlayerWatermarkController::class, 'edit'])->name('watermark.edit');
    Route::put('/marca-de-agua', [\App\Http\Controllers\Admin\PlayerWatermarkController::class, 'update'])->middleware('throttle:10,1')->name('watermark.update');
    Route::delete('/marca-de-agua', [\App\Http\Controllers\Admin\PlayerWatermarkController::class, 'destroy'])->middleware('throttle:10,1')->name('watermark.destroy');
    
    Route::delete('/eventos/{evento}', [EventoController::class, 'destroy'])
        ->middleware('can:delete-event')->name('eventos.destroy');
    Route::get('/eventos/{evento}/grabacion/descargar', [\App\Http\Controllers\RecordingController::class, 'download'])->name('recording.download');
});

// Administrators and streaming operators can create and maintain event details.
// Destruction and access to source recordings remain in the admin-only group above.
Route::middleware(['auth', 'role:admin,operador', 'operator.permission:events'])->prefix('admin')->name('admin.')->group(function () {
    Route::resource('eventos', EventoController::class)->except(['destroy']);
});

// Panel de operación de streaming para administradores y operadores.
Route::middleware(['auth', 'role:admin'])->prefix('streaming')->name('streaming.')->group(function () {
    Route::post('/evento/{evento}/reintentar-grabacion', [\App\Http\Controllers\RecordingController::class, 'retry'])->middleware('throttle:5,1')->name('recording.retry');
});
Route::middleware(['auth', 'role:admin,operador', 'operator.permission:stream'])->prefix('streaming')->name('streaming.')->group(function () {
    Route::post('/evento/{evento}/finalizar', [TransmisionController::class, 'finalizar'])->middleware('throttle:10,1')->name('finalizar');
    Route::get('/control', [TransmisionController::class, 'index'])->name('control');
    Route::post('/evento/{evento}/generar-clave', [TransmisionController::class, 'generarClave'])->name('generar.clave');
    Route::get('/evento/{evento}/estado', [TransmisionController::class, 'estado'])->name('estado');
});
Route::post('/streaming/evento/{evento}/chat', [ChatMessageController::class, 'toggle'])
    ->middleware(['auth', 'role:admin,operador', 'operator.permission:toggle_chat', 'throttle:20,1'])
    ->name('streaming.chat.toggle');

Route::post('/streaming/hooks/disconnected', [\App\Http\Controllers\StreamAuthorizationController::class, 'disconnected'])
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class]);

// Authenticated by a dedicated server token, not a browser session.
Route::post('/streaming/hooks/authorize', \App\Http\Controllers\StreamAuthorizationController::class)
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class])
    ->name('streaming.authorize');

require __DIR__.'/auth.php';
Route::middleware(['auth', 'role:admin'])->prefix('admin/contenido-programado')->group(function () {
    Route::get('/progreso', [\App\Http\Controllers\ScheduledVideoController::class, 'progress'])->name('admin.scheduled.progress');
    Route::post('/publicidad', [\App\Http\Controllers\InstitutionalAdController::class, 'store'])->middleware('throttle:10,1')->name('admin.ads.store');
    Route::post('/publicidad/{ad}/estado', [\App\Http\Controllers\InstitutionalAdController::class, 'toggle'])->name('admin.ads.toggle');
    Route::post('/publicidad/{ad}/reintentar', [\App\Http\Controllers\InstitutionalAdController::class, 'retry'])->middleware('throttle:5,1')->name('admin.ads.retry');
    Route::post('/{evento}/reintentar', [\App\Http\Controllers\ScheduledVideoController::class, 'retry'])->middleware('throttle:5,1')->name('admin.scheduled.retry');
    Route::get('/', [\App\Http\Controllers\ScheduledVideoController::class, 'index'])->name('admin.scheduled.index');
    Route::post('/', [\App\Http\Controllers\ScheduledVideoController::class, 'store'])->middleware('throttle:10,1')->name('admin.scheduled.store');
});
Route::get('/eventos/{evento}/programado/estado', [\App\Http\Controllers\ScheduledVideoController::class, 'state'])->name('scheduled.state');
Route::get('/eventos/{evento}/programado/video', [\App\Http\Controllers\ScheduledVideoController::class, 'media'])->name('scheduled.media');
