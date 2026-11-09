<?php

namespace App\Http\Controllers;

use App\Models\Evento;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

/** Sends each authenticated user to the landing page that matches their role. */
class DashboardRedirectController extends Controller
{
    public function __invoke(): RedirectResponse
    {
        $user = Auth::user();
        $role = trim(strtolower($user->role ?? ''));

        if ($role === 'admin') {
            return redirect()->route('admin.dashboard');
        }
        if ($role === 'operador') {
            return redirect()->route($user->allowsOperatorAction('stream') ? 'streaming.control' : ($user->allowsOperatorAction('events') ? 'admin.eventos.index' : 'channel.index'));
        }

        // Regular users go straight to the public schedule.
        if (Evento::where('estado', 'en_vivo')->exists()) {
            return redirect()->route('eventos.index', ['filtro' => 'en_vivo']);
        }

        return redirect()->route('eventos.index');
    }
}
