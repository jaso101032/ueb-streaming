<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use App\Models\Evento;
use App\Models\Facultad;
use App\Services\ChatFeed;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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
            'carrera_id' => ['nullable', 'integer', 'exists:carreras,id'],
            'categoria_id' => ['nullable', 'integer', 'exists:categorias,id'],
        ], [
            'carrera_id.integer' => 'Selecciona una carrera válida.',
            'carrera_id.exists' => 'La carrera seleccionada no está disponible.',
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
            ->where('estado', '!=', 'cancelado')->visibleTo($request->user());
        $adminCatalog = auth()->user()?->role === 'admin';
        if ($adminCatalog) {
            $viewCounts = DB::table('event_video_views')
                ->select('evento_id', DB::raw('COUNT(*) as catalog_views'))
                ->groupBy('evento_id');
            $query->leftJoinSub($viewCounts, 'catalog_view_counts', function ($join) {
                $join->on('eventos.id', '=', 'catalog_view_counts.evento_id');
            })
                ->select('eventos.*', DB::raw('COALESCE(catalog_view_counts.catalog_views, 0) as catalog_views'))
                ->orderByRaw('COALESCE(catalog_view_counts.catalog_views, 0) DESC');
        }

        $search = trim($filters['q'] ?? '');
        if ($search !== '') {
            // Escapar comodines para buscar el título escrito literalmente.
            $search = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($search));
            $query->whereRaw("LOWER(titulo) LIKE ? ESCAPE '!'", ['%'.$search.'%']);
        }
        foreach (['facultad_id', 'carrera_id', 'categoria_id'] as $field) {
            if (! empty($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }

        $timeline = collect(app(\App\Services\ChannelSchedule::class)->timeline());
        $upcomingIds = $timeline->filter(fn ($i) => $i['state'] === 'upcoming')->keys()->all();
        $endedIds = $timeline->filter(fn ($i) => $i['state'] === 'ended')->keys()->all();
        $upcomingQuery = fn ($q) => $q->whereIn('id', $upcomingIds)->orWhere(fn ($q) => $q->whereDoesntHave('scheduledVideo')->where('estado', 'programado')->where('fecha_inicio', '>=', now()));
        $proximos = (clone $query)->reorder()->where($upcomingQuery)->orderBy('fecha_inicio')->orderBy('id')->limit(4)->get();

        if ($filter === 'en_vivo') {
            $query->where('estado', 'en_vivo');
        } elseif ($filter === 'anteriores') {
            $query->where(fn ($events) => $events->whereIn('id', $endedIds)
                ->orWhereHas('scheduledVideo', fn ($q) => $q->where('on_channel', false))
                ->orWhere(fn ($q) => $q->whereDoesntHave('scheduledVideo')->where(fn ($q) => $q->where('estado', 'finalizado')->orWhereNotNull('video_url'))));
        } elseif ($filter === 'proximos') {
            $query->where($upcomingQuery);
        }

        $eventos = $query->orderBy('fecha_inicio', $filter === 'proximos' ? 'asc' : 'desc')
            ->orderBy('id')->paginate(12)->withQueryString();
        $categorias = Categoria::orderBy('nombre')->get(['id', 'nombre']);
        $facultades = Facultad::orderBy('nombre')->get(['id', 'nombre']);
        $carreras = \App\Models\Carrera::orderBy('nombre')->get(['id', 'nombre', 'facultad_id']);

        return view('eventos.index', compact('eventos', 'filter', 'filters', 'proximos', 'categorias', 'facultades', 'carreras'));
    }

    /**
     * Sugerencias del buscador del catálogo (como en YouTube): títulos que la
     * persona puede ver, sin distinguir mayúsculas ni tildes. Primero los que
     * empiezan como lo escrito, después los que tienen una palabra que empieza
     * así y al final los que lo contienen; entre iguales, los más recientes.
     */
    public function suggestions(Request $request)
    {
        $normalize = fn (string $text) => Str::lower(Str::ascii($text));
        $term = $normalize(trim((string) $request->query('q', '')));
        if (mb_strlen($term) < 2 || mb_strlen($term) > 120) {
            return response()->json([]);
        }
        $candidates = Evento::query()->with('categoria:id,nombre')->visibleTo($request->user())
            ->where('estado', '!=', 'cancelado')->latest('fecha_inicio')->limit(500)
            ->get(['id', 'titulo', 'estado', 'categoria_id', 'fecha_inicio', 'visibilidad', 'miniatura']);
        $ranked = [];
        foreach ($candidates as $index => $event) {
            $title = $normalize($event->titulo);
            $rank = str_starts_with($title, $term) ? 0
                : (preg_match('/(^|[^a-z0-9])'.preg_quote($term, '/').'/', $title) ? 1 : (str_contains($title, $term) ? 2 : null));
            if ($rank !== null) $ranked[] = [$rank, $index, $event];
        }
        usort($ranked, fn ($a, $b) => [$a[0], $a[1]] <=> [$b[0], $b[1]]);

        return response()->json(array_map(fn ($row) => [
            'id' => $row[2]->id,
            'title' => $row[2]->titulo,
            'url' => route('eventos.show', $row[2], false),
            'category' => $row[2]->categoria?->nombre,
            'live' => $row[2]->estado === 'en_vivo',
            'image' => $row[2]->miniatura ? asset('storage/'.$row[2]->miniatura) : null,
            'date' => $row[2]->fecha_inicio?->format('d/m/Y'),
        ], array_slice($ranked, 0, 6)));
    }

    /**
     * Muestra la sala de reproducción en vivo o diferido de un evento específico.
     */
    public function show(Request $request, Evento $evento, ChatFeed $feed)
    {
        if (! $evento->isVisibleTo($request->user())) {
            // Internal content asks for a session; restricted content does not reveal it exists.
            if (! $request->user() && $evento->visibilidad === 'interno') return redirect()->guest(route('login'));
            abort(404);
        }
        $evento->load(['facultad', 'carrera', 'categoria', 'transmision', 'organizador', 'scheduledVideo']);
        // "Más para ver": primero lo que está en vivo, luego lo próximo, luego la
        // misma categoría y por último lo más reciente. Respeta la visibilidad.
        $candidatos = fn () => Evento::with(['facultad', 'carrera', 'categoria', 'transmision', 'organizador', 'scheduledVideo'])
            ->whereKeyNot($evento->id)
            ->where('estado', '!=', 'cancelado')
            ->visibleTo($request->user());
        // Lista "Más de…": los videos de la misma carrera (o, si no tiene, de la
        // misma facultad), del más reciente al más antiguo.
        $playlist = null;
        $group = $evento->carrera ?? $evento->facultad;
        if ($group) {
            $field = $evento->carrera ? 'carrera_id' : 'facultad_id';
            $items = $candidatos()->where($field, $group->id)->where('estado', 'finalizado')->latest('fecha_inicio')->take(12)->get();
            if ($items->isNotEmpty()) {
                $playlist = ['title' => 'Más de '.$group->nombre, 'items' => $items,
                    'url' => route('eventos.index', array_filter(['filtro' => 'anteriores', $field => $group->id,
                        'facultad_id' => $evento->carrera ? $evento->carrera->facultad_id : null]))];
            }
        }
        $eventosRelacionados = $candidatos()->where('estado', 'en_vivo')->latest('fecha_inicio')->take(4)->get()
            ->concat($candidatos()->where('estado', 'programado')->where('fecha_inicio', '>', now())->orderBy('fecha_inicio')->take(4)->get())
            ->concat($evento->categoria_id ? $candidatos()->where('categoria_id', $evento->categoria_id)->latest('fecha_inicio')->take(8)->get() : collect())
            ->concat($candidatos()->latest('fecha_inicio')->take(8)->get())
            ->unique('id')->reject(fn ($e) => $playlist && $playlist['items']->contains('id', $e->id))->take(8)->values();

        $chatMessages = $feed->messages($evento);
        $canModerate = (bool) auth()->user()?->allowsOperatorAction('moderate_chat');
        $videoViews = DB::table('event_video_views')->where('evento_id', $evento->id)->count();

        return view('eventos.show', compact('evento', 'eventosRelacionados', 'chatMessages', 'canModerate', 'videoViews', 'playlist'));
    }
}
