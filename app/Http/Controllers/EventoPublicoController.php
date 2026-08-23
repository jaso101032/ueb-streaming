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
        $filter = request('filtro', 'todos');
        $query = Evento::with(['facultad', 'categoria', 'transmision'])
            ->where('estado', '!=', 'cancelado');

        if ($filter === 'en_vivo') {
            $query->where('estado', 'en_vivo');
        } elseif ($filter === 'anteriores') {
            $query->where(function ($eventos) {
                $eventos->where('estado', 'finalizado')->orWhereNotNull('video_url');
            });
        }

        $eventos = $query->orderBy('fecha_inicio', 'desc')
            ->paginate(12);
        $eventos->withQueryString();

        return view('eventos.index', compact('eventos', 'filter'));
    }

    /**
     * Muestra la sala de reproducción en vivo o diferido de un evento específico.
     */
    public function show(Evento $evento)
    {
        $evento->load(['facultad', 'categoria', 'transmision', 'organizador']);
        $eventosRelacionados = Evento::with(['facultad', 'categoria', 'transmision'])
            ->whereKeyNot($evento->id)
            ->where('estado', '!=', 'cancelado')
            ->latest('fecha_inicio')
            ->take(4)
            ->get();

        return view('eventos.show', compact('evento', 'eventosRelacionados'));
    }
}