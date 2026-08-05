<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\TransmisionController;
use App\Http\Controllers\EventoPublicoController;
use App\Http\Controllers\EventoController;

// 1. La raíz abre directamente el Login
Route::get('/', function () {
    return redirect()->route('login');
});

Route::get('/eventos', [EventoPublicoController::class, 'index'])->name('eventos.index');
Route::get('/eventos/{evento}', [EventoPublicoController::class, 'show'])->name('eventos.show');

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
    
    // Si es estudiante o usuario regular (rol 'user' o vacío)
    return view('welcome'); 
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
    Route::get('/control', [TransmisionController::class, 'index'])->name('control');
    Route::post('/evento/{evento}/generar-clave', [TransmisionController::class, 'generarClave'])->name('generar.clave');
});

require __DIR__.'/auth.php';