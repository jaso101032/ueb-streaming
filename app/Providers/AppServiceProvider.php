<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Una sola regla de contraseña para registro, cambio, recuperación y usuarios.
        \Illuminate\Validation\Rules\Password::defaults(fn () => \Illuminate\Validation\Rules\Password::min(8)->letters()->mixedCase()->numbers()->symbols());
        \Illuminate\Support\Facades\Gate::define('delete-event', fn (\App\Models\User $user) => strtolower(trim((string) $user->role)) === 'admin');
    }
}
