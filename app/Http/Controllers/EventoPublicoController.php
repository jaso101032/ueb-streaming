<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use App\Models\Evento;
use App\Models\Facultad;
use App\Services\ChatFeed;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EventoPublicoController extends Controller
{
    /**
     * Muestra la lista de eventos activos y pasados para la comunidad estudiantil.
     */
    public function index(Request $request)
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'filtro' => ['nullable', Rule::in(['todos', 'en_vivo', 'anteriores', 'proximos'])],
            'facultad_id' => ['nullable', 'integer', 'exists:facultades,id'],
            'categoria_id' => ['nullable', 'integer', 'exists:categorias,id'],
        ], [
            'q.string' => 'Ingresa un título válido para buscar.',
            'q.max' => 'La búsqueda no puede superar 120 caracteres.',
            'filtro.in' => 'Selecciona un estado válido.',
            'facultad_id.integer' => 'Selecciona una facultad válida.',
            'facultad_id.exists' => 'La facultad seleccionada no está disponible.',
            'categoria_id.integer' => 'Selecciona una categoría válida.',
            'categoria_id.exists' => 'La categoría seleccionada no está disponible.',
        ]);
        $filter = $filters['filtro'] ?? 'todos';
        $query = Evento::with(['facultad', 'carrera', 'categoria', 'organizador', 'transmision', 'scheduledVideo'])
            ->where('estado', '!=', 'cancelado');

        $search = trim($filters['q'] ?? '');
        if ($search !== '') {
            // Escapar comodines para buscar el título escrito literalmente.
            $search = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($search));
            $query->whereRaw("LOWER(titulo) LIKE ? ESCAPE '!'", ['%'.$search.'%']);
        }
        foreach (['facultad_id', 'categoria_id'] as $field) {
            if (! empty($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }

        $timeline = collect(app(\App\Services\ChannelSchedule::class)->timeline());
        $upcomingIds = $timeline->filter(fn ($i) => $i['state'] === 'upcoming')->keys()->all();
        $endedIds = $timeline->filter(fn ($i) => $i['state'] === 'ended')->keys()->all();
        $upcomingQuery = fn ($q) => $q->whereIn('id', $upcomingIds)->orWhere(fn ($q) => $q->whereDoesntHave('scheduledVideo')->where('estado', 'programado')->where('fecha_inicio', '>=', now()));
        $proximos = (clone $query)->where($upcomingQuery)->orderBy('fecha_inicio')->orderBy('id')->limit(4)->get();

        if ($filter === 'en_vivo') {
            $query->where('estado', 'en_vivo');
        } elseif ($filter === 'anteriores') {
            $query->where(fn ($events) => $events->whereIn('id', $endedIds)->orWhere(fn ($q) => $q->whereDoesntHave('scheduledVideo')->where(fn ($q) => $q->where('estado', 'finalizado')->orWhereNotNull('video_url'))));
        } elseif ($filter === 'proximos') {
            $query->where($upcomingQuery);
        }

        $eventos = $query->orderBy('fecha_inicio', $filter === 'proximos' ? 'asc' : 'desc')
            ->orderBy('id')->paginate(12)->withQueryString();
        $categorias = Categoria::orderBy('nombre')->get(['id', 'nombre']);
        $facultades = Facultad::orderBy('nombre')->get(['id', 'nombre']);

        return view('eventos.index', compact('eventos', 'filter', 'filters', 'proximos', 'categorias', 'facultades'));
    }

    /**
     * Muestra la sala de reproducción en vivo o diferido de un evento específico.
     */
    public function show(Evento $evento, ChatFeed $feed)
    {
        $evento->load(['facultad', 'carrera', 'categoria', 'transmision', 'organizador', 'scheduledVideo']);
        $eventosRelacionados = Evento::with(['facultad', 'carrera', 'categoria', 'transmision', 'organizador', 'scheduledVideo'])
            ->whereKeyNot($evento->id)
            ->where('estado', '!=', 'cancelado')
            ->latest('fecha_inicio')
            ->take(4)
            ->get();

        $chatMessages = $feed->messages($evento);
        $canModerate = in_array(auth()->user()?->role, ['admin', 'operador'], true);

        return view('eventos.show', compact('evento', 'eventosRelacionados', 'chatMessages', 'canModerate'));
    }
}
