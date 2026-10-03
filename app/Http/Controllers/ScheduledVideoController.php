<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use App\Models\Evento;
use App\Services\ChannelSchedule;
use App\Services\ScheduledVideoProcessor;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ScheduledVideoController extends Controller
{
    public function index()
    {
        return view('admin.scheduled.index', ['events' => Evento::whereHas('scheduledVideo')->with('scheduledVideo')->latest()->paginate(12), 'categories' => Categoria::orderBy('nombre')->get(), 'timeline' => ($timeline = app(ChannelSchedule::class)->timeline()), 'summary' => app(ChannelSchedule::class)->summary($timeline)]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'titulo' => 'required|string|max:255', 'descripcion' => 'nullable|string|max:20000',
            'categoria_id' => 'required|exists:categorias,id',
            'fecha_inicio' => 'required|date_format:Y-m-d\TH:i|after:now',
            'video' => 'required|file|mimes:mp4,mov,avi,mkv,webm,wmv,asf,mpeg,mpg,m4v|max:512000',
        ]);
        try {
            $duration = app(ScheduledVideoProcessor::class)->probe($request->file('video')->getRealPath())['duration'];
        } catch (\Throwable $e) {
            throw ValidationException::withMessages(['video' => 'No se pudo leer el video. Usa MP4, MOV, MKV, WebM, AVI, WMV o MPEG, de hasta 12 horas.']);
        }
        $path = $request->file('video')->store('scheduled-sources', 'local');
        abort_unless($path, 500, 'No se pudo guardar el video.');
        try {
            DB::transaction(function () use ($data, $duration, $path, $request) {
                $event = Evento::create(['titulo' => $data['titulo'], 'descripcion' => $data['descripcion'] ?? null,
                    'categoria_id' => $data['categoria_id'], 'fecha_inicio' => $data['fecha_inicio'],
                    'fecha_fin' => Carbon::parse($data['fecha_inicio'])->addSeconds((int) ceil($duration)),
                    'tipo_evento' => 'Emisión pregrabada', 'estado' => 'programado', 'organizador_id' => $request->user()->id,
                    'slug' => Str::uuid()->toString()]);
                $event->scheduledVideo()->create(['path' => $path, 'duration' => $duration, 'source_path' => $path, 'source_bytes' => $request->file('video')->getSize(), 'processing_status' => 'pending']);
            });
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($path);
            throw $e;
        }

        return to_route('admin.scheduled.index')->with('success', 'Video recibido. Se está preparando para la emisión; aparecerá en la programación cuando esté listo.');
    }

    public function retry(Evento $evento)
    {
        $video = $evento->scheduledVideo;
        abort_unless($video && $video->processing_status === 'failed' && $video->source_path, 409);
        $video->update(['processing_status' => 'pending', 'processing_error' => null]);

        return back()->with('success', 'Conversión pendiente de reintento.');
    }

    public function state(Evento $evento)
    {
        abort_unless($evento->scheduledVideo, 404);

        return response()->json($evento->scheduledVideo->playback())->header('Cache-Control', 'no-store');
    }

    public function media(Evento $evento)
    {
        $scheduled = $evento->scheduledVideo;
        abort_unless($scheduled && $evento->estado !== 'cancelado' && in_array($scheduled->playback()['state'], ['playing', 'paused', 'ended']), 404);
        abort_unless(Storage::disk('local')->exists($scheduled->path), 404);

        return response()->file(Storage::disk('local')->path($scheduled->path), ['Content-Type' => 'video/mp4', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
