<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\MediaLibraryController;
use App\Http\Controllers\Admin\PlayerWatermarkController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\VideoAnalyticsController;
use App\Http\Controllers\AudienceNotificationController;
use App\Http\Controllers\ChannelController;
use App\Http\Controllers\ChatMessageController;
use App\Http\Controllers\DashboardRedirectController;
use App\Http\Controllers\EventoController;
use App\Http\Controllers\EventoPublicoController;
use App\Http\Controllers\InstitutionalAdController;
use App\Http\Controllers\LivePlaybackController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RecordingController;
use App\Http\Controllers\ScheduledVideoController;
use App\Http\Controllers\StreamAuthorizationController;
use App\Http\Controllers\SubtitleController;
use App\Http\Controllers\TransmisionController;
use App\Http\Controllers\VideoViewController;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Route;

// The public channel automatically follows the live signal and scheduled playlist.
Route::get('/', fn () => redirect()->route('channel.index'));
Route::get('/senal', [ChannelController::class, 'index'])->name('channel.index');
Route::get('/senal/estado', [ChannelController::class, 'state'])->name('channel.state');
Route::get('/senal/publicidad/{ad}/video', [InstitutionalAdController::class, 'media'])->name('channel.ad.media');
Route::get('/senal/publicidad/{ad}/subtitulos.vtt', [SubtitleController::class, 'showAd'])->name('channel.ad.subtitles');
Route::get('/eventos/{evento}/subtitulos.vtt', [SubtitleController::class, 'show'])->name('eventos.subtitles');
// Live HLS by public playback id: the OBS stream key never reaches viewers.
Route::get('/directo/{playbackId}.m3u8', [LivePlaybackController::class, 'playlist'])
    ->where('playbackId', '[a-z0-9]{32}')->name('live.playlist');
Route::get('/directo/{playbackId}/{sequence}.ts', [LivePlaybackController::class, 'segment'])
    ->where(['playbackId' => '[a-z0-9]{32}', 'sequence' => '[0-9]{1,12}'])->name('live.segment');

Route::get('/eventos', [EventoPublicoController::class, 'index'])->name('eventos.index');
Route::get('/eventos/{evento}/reproduccion', [RecordingController::class, 'state'])->name('eventos.playback-state');
Route::get('/eventos/{evento}/grabacion', [RecordingController::class, 'show'])->name('eventos.recording');
// Presence is anonymous telemetry, not an account action. It must work from
// every public viewer (including phones behind the local HLS proxy), so it
// cannot rely on a browser session's CSRF token. Rate limiting remains active.
Route::post('/eventos/{evento}/espectadores', [TransmisionController::class, 'viewerHeartbeat'])
    ->middleware('throttle:30,1')
    ->withoutMiddleware([PreventRequestForgery::class])
    ->name('eventos.viewers');
// A view is recorded only after the browser has actually played a video.
Route::post('/eventos/{evento}/visualizaciones', [VideoViewController::class, 'store'])
    ->middleware('throttle:120,1')
    ->withoutMiddleware([PreventRequestForgery::class])
    ->name('eventos.video-views');
Route::get('/eventos/{evento}', [EventoPublicoController::class, 'show'])->name('eventos.show');
Route::get('/eventos/{evento}/chat', [ChatMessageController::class, 'index'])->name('eventos.chat.index');
Route::get('/eventos/{evento}/chat/replay', [ChatMessageController::class, 'replay'])->name('eventos.chat.replay');
Route::post('/eventos/{evento}/chat', [ChatMessageController::class, 'store'])->middleware(['auth', 'throttle:30,1'])->name('eventos.chat.store');

Route::delete('/eventos/{evento}/chat/{message}', [ChatMessageController::class, 'destroy'])->middleware(['auth', 'role:admin,operador', 'operator.permission:moderate_chat', 'throttle:60,1'])->name('eventos.chat.destroy');

// Post-login landing: redirects according to the user's role.
Route::get('/dashboard', DashboardRedirectController::class)->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::get('/mis-notificaciones', [AudienceNotificationController::class, 'index'])->middleware('throttle:60,1')->name('notifications.index');
    Route::put('/mis-notificaciones/preferencias', [AudienceNotificationController::class, 'preferences'])->middleware('throttle:20,1')->name('notifications.preferences');
    Route::patch('/mis-notificaciones/{id}/leer', [AudienceNotificationController::class, 'read'])->middleware('throttle:60,1')->name('notifications.read');
    Route::post('/mis-notificaciones/push', [AudienceNotificationController::class, 'subscribe'])->middleware('throttle:10,1')->name('notifications.push.subscribe');
    Route::delete('/mis-notificaciones/push', [AudienceNotificationController::class, 'unsubscribe'])->middleware('throttle:10,1')->name('notifications.push.unsubscribe');
});

// Panel de Administrador / Operador para la Gestión de Eventos
// Funciones de gestión exclusivas del administrador.
Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/medios', [MediaLibraryController::class, 'index'])->name('media.index');
    Route::get('/medios/programados/{evento}/video', [ScheduledVideoController::class, 'adminPreview'])->name('media.scheduled-preview');
    Route::get('/medios/publicidad/{ad}/video', [InstitutionalAdController::class, 'adminPreview'])->name('media.ad-preview');
    Route::get('/usuarios', [UserController::class, 'index'])->name('usuarios.index');
    Route::get('/eventos/{evento}/estadisticas', [VideoAnalyticsController::class, 'show'])->name('eventos.analytics');
    Route::post('/usuarios', [UserController::class, 'store'])->middleware('throttle:10,1')->name('usuarios.store');
    Route::put('/usuarios/{usuario}', [UserController::class, 'update'])->middleware('throttle:20,1')->name('usuarios.update');
    Route::delete('/usuarios/{usuario}', [UserController::class, 'destroy'])->middleware('throttle:10,1')->name('usuarios.destroy');
    Route::get('/marca-de-agua', [PlayerWatermarkController::class, 'edit'])->name('watermark.edit');
    Route::put('/marca-de-agua', [PlayerWatermarkController::class, 'update'])->middleware('throttle:10,1')->name('watermark.update');
    Route::put('/marca-de-agua/posicion', [PlayerWatermarkController::class, 'updatePosition'])->middleware('throttle:20,1')->name('watermark.position');
    Route::delete('/marca-de-agua', [PlayerWatermarkController::class, 'destroy'])->middleware('throttle:10,1')->name('watermark.destroy');
    
    Route::delete('/eventos/{evento}', [EventoController::class, 'destroy'])
        ->middleware('can:delete-event')->name('eventos.destroy');
    Route::get('/eventos/{evento}/grabacion/descargar', [RecordingController::class, 'download'])->name('recording.download');
    Route::post('/eventos/{evento}/grabacion/aprobar', [RecordingController::class, 'approve'])->middleware('throttle:20,1')->name('recording.approve');
    Route::post('/eventos/{evento}/grabacion/retirar', [RecordingController::class, 'withdraw'])->middleware('throttle:20,1')->name('recording.withdraw');
    Route::get('/auditoria', [\App\Http\Controllers\Admin\AuditLogController::class, 'index'])->name('audit.index');
    Route::post('/eventos/{evento}/subtitulos', [SubtitleController::class, 'upload'])->middleware('throttle:20,1')->name('subtitles.store');
    Route::delete('/eventos/{evento}/subtitulos', [SubtitleController::class, 'remove'])->middleware('throttle:20,1')->name('subtitles.destroy');
    Route::post('/contenido-programado/publicidad/{ad}/subtitulos', [SubtitleController::class, 'uploadAd'])->middleware('throttle:20,1')->name('ads.subtitles.store');
    Route::delete('/contenido-programado/publicidad/{ad}/subtitulos', [SubtitleController::class, 'removeAd'])->middleware('throttle:20,1')->name('ads.subtitles.destroy');
});

// Administrators and streaming operators can create and maintain event details.
// Destruction and access to source recordings remain in the admin-only group above.
Route::middleware(['auth', 'role:admin,operador', 'operator.permission:events'])->prefix('admin')->name('admin.')->group(function () {
    Route::resource('eventos', EventoController::class)->except(['destroy']);
});

// Panel de operación de streaming para administradores y operadores.
Route::middleware(['auth', 'role:admin'])->prefix('streaming')->name('streaming.')->group(function () {
    Route::post('/evento/{evento}/reintentar-grabacion', [RecordingController::class, 'retry'])->middleware('throttle:5,1')->name('recording.retry');
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

Route::post('/streaming/hooks/disconnected', [StreamAuthorizationController::class, 'disconnected'])
    ->withoutMiddleware([PreventRequestForgery::class]);

// Authenticated by a dedicated server token, not a browser session.
Route::post('/streaming/hooks/authorize', StreamAuthorizationController::class)
    ->withoutMiddleware([PreventRequestForgery::class])
    ->name('streaming.authorize');

Route::middleware(['auth', 'role:admin'])->prefix('admin/contenido-programado')->group(function () {
    Route::get('/progreso', [ScheduledVideoController::class, 'progress'])->name('admin.scheduled.progress');
    Route::post('/publicidad', [InstitutionalAdController::class, 'store'])->middleware('throttle:10,1')->name('admin.ads.store');
    Route::post('/publicidad/{ad}/estado', [InstitutionalAdController::class, 'toggle'])->name('admin.ads.toggle');
    Route::post('/publicidad/{ad}/reintentar', [InstitutionalAdController::class, 'retry'])->middleware('throttle:5,1')->name('admin.ads.retry');
    Route::post('/{evento}/reintentar', [ScheduledVideoController::class, 'retry'])->middleware('throttle:5,1')->name('admin.scheduled.retry');
    Route::get('/', [ScheduledVideoController::class, 'index'])->name('admin.scheduled.index');
    Route::post('/', [ScheduledVideoController::class, 'store'])->middleware('throttle:10,1')->name('admin.scheduled.store');
});
Route::get('/eventos/{evento}/programado/estado', [ScheduledVideoController::class, 'state'])->name('scheduled.state');
Route::get('/eventos/{evento}/programado/video', [ScheduledVideoController::class, 'media'])->name('scheduled.media');

require __DIR__.'/auth.php';
