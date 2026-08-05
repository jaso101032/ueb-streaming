<?php

namespace App\Http\Controllers;

use App\Models\Evento;
use Illuminate\Http\Request;

class EventoPublicoController extends Controller
{
    /**
     * Muestra la lista de eventos activos y pasados para la comunidad estudiantil.
     */
    public function index()
    {
        $eventos = Evento::with(['facultad', 'categoria', 'transmision'])
            ->orderBy('fecha_inicio', 'desc')
            ->paginate(12);

        return view('eventos.index', compact('eventos'));
    }

    /**
     * Muestra la sala de reproducción en vivo o diferido de un evento específico.
     */
    public function show(Evento $evento)
    {
        $evento->load(['facultad', 'categoria', 'transmision', 'organizador']);

        return view('eventos.show', compact('evento'));
    }
}