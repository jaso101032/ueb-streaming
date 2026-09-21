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

// 1. La raíz abre la transmisión activa o el catálogo público.
Route::get('/', function () {
    $eventoEnVivo = Evento::where('estado', 'en_vivo')
        ->latest('fecha_inicio')
        ->first();

    return $eventoEnVivo
        ? redirect()->route('eventos.show', $eventoEnVivo)
        : redirect()->route('eventos.index');
});

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
    
    if ($role === 'operador') {
        return redirect()->route('streaming.control');
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
// Modificado el middleware para permitir 'admin' y 'operador' en las rutas de administración necesarias
Route::middleware(['auth', 'role:admin,operador'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    
    // CRUD completo de Eventos accesible por Administrador y Operador
    Route::resource('eventos', EventoController::class);
});

// Panel de Control de Streaming para Operador / Admin
Route::middleware(['auth', 'role:admin,operador'])->prefix('streaming')->name('streaming.')->group(function () {
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