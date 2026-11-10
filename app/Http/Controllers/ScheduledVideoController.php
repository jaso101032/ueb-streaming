<?php

namespace App\Http\Controllers;

use App\Support\Audit;
use App\Models\Categoria;
use App\Models\Evento;
use App\Models\InstitutionalAd;
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
        return view('admin.scheduled.index', ['events' => Evento::whereHas('scheduledVideo')->with('scheduledVideo')->latest()->paginate(12),
            'ads' => InstitutionalAd::latest()->get(), 'categories' => Categoria::orderBy('nombre')->get(),
            'timeline' => ($timeline = app(ChannelSchedule::class)->timeline()), 'summary' => app(ChannelSchedule::class)->summary($timeline, true)]);
    }

    public function progress(Request $request)
    {
        $page = max(1, (int) $request->query('page', 1));
        $events = Evento::whereHas('scheduledVideo')->with('scheduledVideo')->latest()->paginate(12, ['*'], 'page', $page);

        return response()->json([
            'events' => $events->mapWithKeys(fn ($event) => [$event->id => [
                'status' => $event->scheduledVideo->processing_status,
                'progress' => $event->scheduledVideo->processing_progress,
                'error' => $event->scheduledVideo->processing_error,
            ]]),
            'ads' => InstitutionalAd::get()->mapWithKeys(fn ($ad) => [$ad->id => [
                'status' => $ad->processing_status,
                'progress' => $ad->processing_progress,
                'error' => $ad->processing_error,
            ]]),
        ])->header('Cache-Control', 'no-store');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'titulo' => 'required|string|max:255', 'descripcion' => 'nullable|string|max:20000',
            'categoria_id' => 'required|exists:categorias,id',
            'fecha_inicio' => 'required|date_format:Y-m-d\TH:i|after:now',
            'video' => 'required|file|mimes:mp4,mov,avi,mkv,webm,wmv,asf,mpeg,mpg,m4v|max:512000',
            'miniatura' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:max_width=4096,max_height=4096'],
        ]);
        try {
            $duration = app(ScheduledVideoProcessor::class)->probe($request->file('video')->getRealPath())['duration'];
        } catch (\Throwable $e) {
            throw ValidationException::withMessages(['video' => 'No se pudo leer el video. Usa MP4, MOV, MKV, WebM, AVI, WMV o MPEG, de hasta 12 horas.']);
        }
        $path = $request->file('video')->store('scheduled-sources', 'local');
        abort_unless($path, 500, 'No se pudo guardar el video.');
        $thumbnailPath = null;
        try {
            if ($request->hasFile('miniatura')) {
                $thumbnailPath = $request->file('miniatura')->store('eventos/miniaturas', 'public');
                abort_unless($thumbnailPath, 500, 'No se pudo guardar la imagen de portada.');
            }
            $event = DB::transaction(function () use ($data, $duration, $path, $thumbnailPath, $request) {
                $event = Evento::create(['titulo' => $data['titulo'], 'descripcion' => $data['descripcion'] ?? null,
                    'categoria_id' => $data['categoria_id'], 'fecha_inicio' => $data['fecha_inicio'],
                    'fecha_fin' => Carbon::parse($data['fecha_inicio'])->addSeconds((int) ceil($duration)),
                    'tipo_evento' => 'Emisión pregrabada', 'estado' => 'programado', 'miniatura' => $thumbnailPath, 'organizador_id' => $request->user()->id,
                    'slug' => Str::uuid()->toString()]);
                $event->scheduledVideo()->create(['path' => $path, 'duration' => $duration, 'source_path' => $path, 'source_bytes' => $request->file('video')->getSize(), 'processing_status' => 'pending']);

                return $event;
            });
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($path);
            if ($thumbnailPath) {
                Storage::disk('public')->delete($thumbnailPath);
            }
            throw $e;
        }

        Audit::record('programacion.creada', $event);
        $message = 'Video recibido. Se está preparando para la emisión; aparecerá en la programación cuando esté listo.';
        if ($request->expectsJson()) {
            $request->session()->flash('success', $message);
            return response()->json(['message' => $message, 'redirect' => route('admin.scheduled.index')], 201);
        }

        return to_route('admin.scheduled.index')->with('success', $message);
    }

    public function retry(Evento $evento)
    {
        $video = $evento->scheduledVideo;
        abort_unless($video && $video->processing_status === 'failed' && $video->source_path, 409);
        $video->update(['processing_status' => 'pending', 'processing_progress' => 0,
            'processing_heartbeat_at' => null, 'processing_error' => null]);

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
        return $this->serveVideo($scheduled);
    }

    public function adminPreview(Evento $evento)
    {
        $scheduled = $evento->scheduledVideo;
        abort_unless($scheduled && $scheduled->processing_status === 'ready', 404);
        return $this->serveVideo($scheduled);
    }

    private function serveVideo($scheduled)
    {
        abort_unless(Storage::disk('local')->exists($scheduled->path), 404);

        if (config('streaming.x_accel_media') && preg_match('~^scheduled-videos/([a-f0-9-]{36}\.mp4)$~D', $scheduled->path, $matches)) {
            return response('', 200, ['X-Accel-Redirect' => '/_protected_media/scheduled-videos/'.$matches[1],
                'Content-Type' => 'video/mp4', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
        }

        return response()->file(Storage::disk('local')->path($scheduled->path), ['Content-Type' => 'video/mp4', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
