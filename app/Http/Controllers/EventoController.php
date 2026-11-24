<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Http\Requests\EventoRequest;
use App\Models\Carrera;
use App\Models\Categoria;
use App\Models\Evento;
use App\Models\Facultad;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use App\Support\Audit;
use Throwable;

class EventoController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'estado' => ['nullable', 'in:en_vivo,programado,finalizado,cancelado'],
        ]);
        $search = trim($filters['q'] ?? '');
        $base = Evento::query()->when($search !== '', function ($query) use ($search) {
            $term = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($search));
            $query->whereRaw("LOWER(titulo) LIKE ? ESCAPE '!'", ['%'.$term.'%']);
        });
        // Conteos por estado (con la búsqueda aplicada) para las pestañas.
        $counts = ['todos' => (clone $base)->count()] + (clone $base)->selectRaw('estado, COUNT(*) as total')
            ->groupBy('estado')->pluck('total', 'estado')->map(fn ($n) => (int) $n)->all();
        $eventos = $base->with(['facultad', 'carrera', 'categoria', 'organizador', 'transmision', 'scheduledVideo'])
            ->when($filters['estado'] ?? null, fn ($query, $estado) => $query->where('estado', $estado))
            ->latest()->paginate(10)->withQueryString();

        return view('admin.eventos.index', ['eventos' => $eventos, 'filters' => $filters, 'counts' => $counts]);
    }

    private function formData(Evento $evento): array
    {
        $evento->loadMissing('transmision');
        return [
            'evento' => $evento,
            'categorias' => Categoria::orderBy('nombre')->get(),
            'facultades' => Facultad::orderBy('nombre')->get(),
            'carreras' => Carrera::orderBy('nombre')->get(),
            'tipos' => collect(['Graduaciones', 'Sesiones solemnes', 'Conferencias', 'Seminarios', 'Defensas de tesis', 'Eventos culturales', 'Eventos deportivos', 'Actos institucionales', 'Rendición de cuentas', $evento->tipo_evento])->filter()->unique(),
        ];
    }

    public function create()
    {
        return view('admin.eventos.create', $this->formData(new Evento));
    }

    public function store(EventoRequest $request)
    {
        abort_if($request->has('chat_enabled') && ! $request->boolean('chat_enabled') && ! $request->user()->allowsOperatorAction('toggle_chat'), 403);
        $data = $request->safe()->except('miniatura', 'recording_fps_limit');
        $thumbnail = $this->storeThumbnail($request);

        try {
            $evento = DB::transaction(function () use ($request, $data, $thumbnail) {
                $evento = Evento::create(array_merge($data, [
                    'slug' => Str::limit(Str::slug($data['titulo']), 210, '').'-'.Str::uuid(),
                    'organizador_id' => $request->user()->id,
                    'estado' => 'programado',
                    'miniatura' => $thumbnail,
                ]));
                $key = 'ueb_'.Str::random(32);
                $stream = $evento->transmision()->create([
                    'stream_key' => $key,
                    'rtmp_server' => config('streaming.rtmp_url') ?: 'rtmp://'.$request->getHost().':1935/live',
                    'hls_url' => null,
                    'activo' => false,
                    'recording_fps_limit' => $request->input('recording_fps_limit') ?: null,
                ]);
                $stream->update(['hls_url' => $stream->playbackUrl()]);

                return $evento;
            });
        } catch (Throwable $exception) {
            if ($thumbnail) {
                Storage::disk('public')->delete($thumbnail);
            }
            throw $exception;
        }

        Audit::record('evento.creado', $evento, ['visibilidad' => $evento->visibilidad, 'licencia' => $evento->licencia, 'autorizacion' => $evento->autorizacion_referencia, 'autorizacion_vigente' => $evento->hasValidAuthorization()]);

        // Quien puede emitir va directo a "Emitir con OBS", donde están la dirección
        // del servidor y la clave del evento recién creado.
        $destination = $request->user()->allowsOperatorAction('stream')
            ? redirect()->route('streaming.control', ['evento' => $evento->id])
            : redirect()->route('admin.eventos.show', $evento);

        return $destination->with('success', 'Evento creado y clave de OBS generada correctamente.');
    }

    public function show(Evento $evento)
    {
        $evento->load(['transmision', 'facultad', 'carrera', 'categoria', 'organizador']);

        return view('admin.eventos.show', compact('evento'));
    }

    public function edit(Evento $evento)
    {
        return view('admin.eventos.edit', $this->formData($evento));
    }

    public function update(EventoRequest $request, Evento $evento)
    {
        if ($request->has('chat_enabled') && (bool) $request->boolean('chat_enabled') !== (bool) $evento->chat_enabled) {
            abort_unless($request->user()->allowsOperatorAction('toggle_chat'), 403);
        }
        if ($request->has('estado') && $request->input('estado') !== $evento->estado) {
            abort_unless($request->user()->allowsOperatorAction('stream'), 403);
        }
        $data = $request->safe()->except('miniatura', 'recording_fps_limit');
        $stream = $evento->transmision;
        if ($request->exists('recording_fps_limit') && $stream?->iniciado_en) {
            if ((int) $request->input('recording_fps_limit') !== (int) $stream->recording_fps_limit) {
                throw ValidationException::withMessages(['recording_fps_limit' => 'No se puede cambiar el límite de FPS después de iniciar la transmisión.']);
            }
        }
        $changes = [];
        $thumbnail = $this->storeThumbnail($request);
        $previousThumbnail = $evento->miniatura;
        if ($thumbnail) {
            $data['miniatura'] = $thumbnail;
        }

        try {
            DB::transaction(function () use ($evento, $data, $request, $stream, &$changes) {
                $evento->fill($data);
                $changes = collect($evento->getDirty())->except(['updated_at', 'miniatura'])
                    ->map(fn ($value, $field) => ['antes' => self::auditValue($evento->getRawOriginal($field)), 'despues' => self::auditValue($evento->getAttributes()[$field] ?? null)])->all();
                $evento->save();
                if ($request->exists('recording_fps_limit') && $stream && ! $stream->iniciado_en) {
                    $stream->update(['recording_fps_limit' => $request->input('recording_fps_limit') ?: null]);
                }
            });
        } catch (Throwable $exception) {
            if ($thumbnail) {
                Storage::disk('public')->delete($thumbnail);
            }
            throw $exception;
        }

        if ($thumbnail && $previousThumbnail && str_starts_with($previousThumbnail, 'eventos/miniaturas/')) {
            Storage::disk('public')->delete($previousThumbnail);
        }

        if ($changes || $thumbnail) {
            Audit::record('evento.actualizado', $evento, array_filter(['cambios' => $changes, 'miniatura' => $thumbnail ? 'reemplazada' : null]));
        }

        return redirect()->route('admin.eventos.show', $evento)->with('success', 'Evento actualizado correctamente.');
    }

    private static function auditValue($value)
    {
        return is_string($value) ? mb_substr($value, 0, 200) : $value;
    }

    private function storeThumbnail(EventoRequest $request): ?string
    {
        if (! $request->hasFile('miniatura')) {
            return null;
        }

        $path = $request->file('miniatura')->store('eventos/miniaturas', 'public');
        if (! $path) {
            throw ValidationException::withMessages(['miniatura' => 'No se pudo guardar la miniatura. Inténtalo de nuevo.']);
        }

        return $path;
    }

    public function destroy(Evento $evento, \App\Services\StorageCleanup $cleanup)
    {
        \Illuminate\Support\Facades\Gate::authorize('delete-event');
        // Eliminar es borrar todo: el evento, su video y sus archivos.
        if ($reason = $cleanup->inUse($evento)) {
            return back(303)->withErrors(['evento' => $reason]);
        }
        $bytes = $cleanup->eventBytes($evento);
        $cleanup->purgeEvent($evento);
        \App\Support\StorageUsage::flush();

        return redirect()->route('admin.eventos.index')->with('success', $bytes > 0
            ? 'Evento eliminado con su video y archivos. Se liberaron '.\App\Services\StorageCleanup::human($bytes).'.'
            : 'Evento eliminado.');
    }
}
