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

Route::get('/eventos', [EventoPublicoController::class, 'index'])->name('eventos.index');
Route::get('/eventos/{evento}/reproduccion', [\App\Http\Controllers\RecordingController::class, 'state'])->name('eventos.playback-state');
Route::get('/eventos/{evento}/grabacion', [\App\Http\Controllers\RecordingController::class, 'show'])->name('eventos.recording');
Route::get('/eventos/{evento}', [EventoPublicoController::class, 'show'])->name('eventos.show');
Route::get('/eventos/{evento}/chat', [ChatMessageController::class, 'index'])->name('eventos.chat.index');
Route::post('/eventos/{evento}/chat', [ChatMessageController::class, 'store'])->middleware(['auth', 'throttle:30,1'])->name('eventos.chat.store');

Route::delete('/eventos/{evento}/chat/{message}', [ChatMessageController::class, 'destroy'])->middleware(['auth', 'role:admin,operador', 'throttle:60,1'])->name('eventos.chat.destroy');

// 2. Página de inicio general post-login (Redirige según el rol exacto)
Route::get('/dashboard', function () {
    $user = Auth::user();
    $role = trim(strtolower($user->role ?? ''));
    
    if ($role === 'admin') {
        return redirect()->route('admin.dashboard');
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
    
    // CRUD completo de Eventos accesible por Administrador y Operador
    Route::resource('eventos', EventoController::class)->except('destroy');
    Route::delete('/eventos/{evento}', [EventoController::class, 'destroy'])
        ->middleware('can:delete-event')->name('eventos.destroy');
});

// Panel de Control de Streaming para Operador / Admin
Route::middleware(['auth', 'role:admin'])->prefix('streaming')->name('streaming.')->group(function () {
    Route::post('/evento/{evento}/finalizar', [TransmisionController::class, 'finalizar'])->middleware('throttle:10,1')->name('finalizar');
    Route::post('/evento/{evento}/reintentar-grabacion', [\App\Http\Controllers\RecordingController::class, 'retry'])->middleware('throttle:5,1')->name('recording.retry');
    Route::get('/control', [TransmisionController::class, 'index'])->name('control');
    Route::post('/evento/{evento}/generar-clave', [TransmisionController::class, 'generarClave'])->name('generar.clave');
    Route::get('/evento/{evento}/estado', [TransmisionController::class, 'estado'])->name('estado');
});

Route::post('/streaming/hooks/disconnected', [\App\Http\Controllers\StreamAuthorizationController::class, 'disconnected'])
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class]);

// Authenticated by a dedicated server token, not a browser session.
Route::post('/streaming/hooks/authorize', \App\Http\Controllers\StreamAuthorizationController::class)
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class])
    ->name('streaming.authorize');

require __DIR__.'/auth.php';
Route::middleware(['auth', 'role:admin'])->prefix('admin/contenido-programado')->group(function () {
    Route::post('/{evento}/reintentar', [\App\Http\Controllers\ScheduledVideoController::class, 'retry'])->middleware('throttle:5,1')->name('admin.scheduled.retry');
    Route::get('/', [\App\Http\Controllers\ScheduledVideoController::class, 'index'])->name('admin.scheduled.index');
    Route::post('/', [\App\Http\Controllers\ScheduledVideoController::class, 'store'])->middleware('throttle:10,1')->name('admin.scheduled.store');
});
Route::get('/eventos/{evento}/programado/estado', [\App\Http\Controllers\ScheduledVideoController::class, 'state'])->name('scheduled.state');
Route::get('/eventos/{evento}/programado/video', [\App\Http\Controllers\ScheduledVideoController::class, 'media'])->name('scheduled.media');
