<?php

namespace App\Http\Controllers;

use App\Http\Requests\EventoRequest;
use App\Models\Carrera;
use App\Models\Categoria;
use App\Models\Evento;
use App\Models\Facultad;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class EventoController extends Controller
{
    public function index()
    {
        $eventos = Evento::with(['facultad', 'carrera', 'categoria', 'organizador', 'transmision', 'scheduledVideo'])
            ->latest()->paginate(10);

        return view('admin.eventos.index', compact('eventos'));
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
                $evento->transmision()->create([
                    'stream_key' => $key,
                    'rtmp_server' => config('streaming.rtmp_url') ?: 'rtmp://'.$request->getHost().':1935/live',
                    'hls_url' => (config('streaming.hls_base_url') ?: 'http://'.$request->getHost().':8080/hls').'/'.$key.'.m3u8',
                    'activo' => false,
                    'recording_fps_limit' => $request->input('recording_fps_limit') ?: null,
                ]);

                return $evento;
            });
        } catch (Throwable $exception) {
            if ($thumbnail) {
                Storage::disk('public')->delete($thumbnail);
            }
            throw $exception;
        }

        return redirect()->route('admin.eventos.show', $evento)->with('success', 'Evento creado y clave de OBS generada correctamente.');
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
        $thumbnail = $this->storeThumbnail($request);
        $previousThumbnail = $evento->miniatura;
        if ($thumbnail) {
            $data['miniatura'] = $thumbnail;
        }

        try {
            DB::transaction(function () use ($evento, $data, $request, $stream) {
                $evento->update($data);
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

        return redirect()->route('admin.eventos.show', $evento)->with('success', 'Evento actualizado correctamente.');
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

    public function destroy(Evento $evento)
    {
        \Illuminate\Support\Facades\Gate::authorize('delete-event');
        $evento->delete();

        return redirect()->route('admin.eventos.index')->with('success', 'Evento eliminado correctamente.');
    }
}
