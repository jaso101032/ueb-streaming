<?php

namespace App\Http\Controllers;

use App\Models\Evento;
use App\Models\Transmision;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class EventoController extends Controller
{
    /**
     * Muestra la lista de eventos en el panel administrativo.
     */
    public function index()
    {
        $eventos = Evento::with(['facultad', 'carrera', 'categoria', 'organizador', 'transmision'])
            ->latest()
            ->paginate(10);

        return view('admin.eventos.index', compact('eventos'));
    }

    /**
     * Muestra el formulario para crear un nuevo evento.
     */
    public function create()
    {
        // Aquí puedes pasar las categorías, facultades y carreras para los selects de la vista
        return view('admin.eventos.create');
    }

    /**
     * Almacena un nuevo evento y le genera su clave de transmisión automática.
     */
    public function store(Request $request)
    {
        $request->validate([
            'titulo' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'tipo_evento' => 'required|string',
            'fecha_inicio' => 'required|date',
            'categoria_id' => 'required|exists:categorias,id',
            'facultad_id' => 'nullable|exists:facultades,id',
            'carrera_id' => 'nullable|exists:carreras,id',
        ]);

        // Usamos una transacción para asegurar que se cree el evento y su transmisión juntos
        DB::transaction(function () use ($request) {
            // 1. Crear el evento
            $evento = Evento::create([
                'titulo' => $request->titulo,
                'slug' => Str::slug($request->titulo) . '-' . time(),
                'descripcion' => $request->descripcion,
                'tipo_evento' => $request->tipo_evento,
                'fecha_inicio' => $request->fecha_inicio,
                'fecha_fin' => $request->fecha_fin,
                'lugar' => $request->lugar,
                'organizador_id' => auth()->id(), // Usuario autenticado actual
                'facultad_id' => $request->facultad_id,
                'carrera_id' => $request->carrera_id,
                'categoria_id' => $request->categoria_id,
                'estado' => 'programado',
            ]);

            // 2. Generar automáticamente la clave única para OBS
            Transmision::create([
                'evento_id' => $evento->id,
                'stream_key' => 'ueb_' . Str::random(30), // Genera formato tipo ueb_SJgrksUjzPcL...
                'rtmp_server' => 'rtmp://192.168.100.7/live', // Ajusta tu IP o servidor si es necesario
                'activo' => false,
            ]);
        });

        return redirect()->route('eventos.index')->with('success', '¡Evento creado y clave de OBS generada con éxito!');
    }

    /**
     * Muestra los detalles de un evento específico.
     */
    public function show(Evento $evento)
    {
        $evento->load(['transmision', 'facultad', 'carrera', 'categoria', 'organizador']);
        return view('admin.eventos.show', compact('evento'));
    }

    /**
     * Muestra el formulario para editar un evento existente.
     */
    public function edit(Evento $evento)
    {
        return view('admin.eventos.edit', compact('evento'));
    }

    /**
     * Actualiza la información del evento.
     */
    public function update(Request $request, Evento $evento)
    {
        $request->validate([
            'titulo' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'tipo_evento' => 'required|string',
            'fecha_inicio' => 'required|date',
            'categoria_id' => 'required|exists:categorias,id',
        ]);

        $evento->update([
            'titulo' => $request->titulo,
            'slug' => Str::slug($request->titulo) . '-' . $evento->id,
            'descripcion' => $request->descripcion,
            'tipo_evento' => $request->tipo_evento,
            'fecha_inicio' => $request->fecha_inicio,
            'fecha_fin' => $request->fecha_fin,
            'lugar' => $request->lugar,
            'facultad_id' => $request->facultad_id,
            'carrera_id' => $request->carrera_id,
            'categoria_id' => $request->categoria_id,
            'estado' => $request->estado ?? $evento->estado,
        ]);

        return redirect()->route('eventos.index')->with('success', '¡Evento actualizado correctamente!');
    }

    /**
     * Elimina el evento (Soft Delete).
     */
    public function destroy(Evento $evento)
    {
        $evento->delete();
        return redirect()->route('eventos.index')->with('success', '¡Evento eliminado correctamente!');
    }
}