<?php

namespace App\Http\Controllers;

use App\Models\Evento;
use App\Services\AudienceAnnouncements;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class RecordingController extends Controller
{
    public function show(Request $request, Evento $evento)
    {
        $path = $this->playablePath($request, $evento);
        abort_unless($path, 404);
        if (config('streaming.x_accel_media')) {
            return response('', 200, ['X-Accel-Redirect' => '/_protected_media/recordings/'.basename($path),
                'Content-Type' => 'video/mp4', 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, max-age=3600']);
        }
        // BinaryFileResponse supports byte ranges for seeking without loading the file in memory.
        return response()->file($path, ['Content-Type' => 'video/mp4',
            'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, max-age=3600']);
    }

    public function state(Request $request, Evento $evento)
    {
        abort_unless($evento->isVisibleTo($request->user()), 404);

        return response()->json(['state' => $evento->estado,
            'ready' => (bool) $this->playablePath($request, $evento)])
            ->header('Cache-Control', 'no-store');
    }

    /** The protected download endpoint is deliberately separate from public playback. */
    public function download(Evento $evento)
    {
        abort_unless(auth()->user()?->role === 'admin', 403);
        $path = $evento->transmision?->recordingPath();
        abort_unless($evento->estado === 'finalizado' && $path, 404);
        Audit::record('grabacion.descargada', $evento);

        return response()->download($path, 'uebtv-'.str($evento->slug ?: $evento->id)->slug().'.mp4', [
            'Content-Type' => 'video/mp4', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function retry(Evento $evento)
    {
        DB::transaction(function () use ($evento) {
            $event = Evento::whereKey($evento->id)->lockForUpdate()->firstOrFail();
            $stream = $event->transmision()->lockForUpdate()->first();
            abort_unless($event->estado === 'finalizado' && $stream?->recording_status === 'failed', 409);
            $stream->update(['recording_attempts' => 0, 'recording_status' => 'waiting', 'recording_error' => null]);
        });
        Audit::record('grabacion.reintento', $evento);

        return back()->with('success', 'La grabación se volverá a preparar automáticamente.');
    }

    /** Editorial approval: a technically ready recording becomes visible to its audience. */
    public function approve(Request $request, Evento $evento)
    {
        $previous = $this->review($request, $evento, 'aprobada');
        if ($previous === 'pendiente') {
            try { app(AudienceAnnouncements::class)->publish($evento, 'video'); }
            catch (Throwable $exception) { report($exception); }
        }

        return back()->with('success', 'Grabación aprobada y publicada.');
    }

    /** Withdrawal revokes playback immediately, including direct MP4 URLs. */
    public function withdraw(Request $request, Evento $evento)
    {
        $this->review($request, $evento, 'retirada');

        return back()->with('success', 'Grabación retirada. Ya no está disponible para el público.');
    }

    private function review(Request $request, Evento $evento, string $status): string
    {
        $previous = DB::transaction(function () use ($request, $evento, $status) {
            $stream = $evento->transmision()->lockForUpdate()->first();
            abort_unless($evento->estado === 'finalizado' && $stream?->recordingPath(), 409);
            $previous = $stream->publicacion;
            abort_if($previous === $status, 409);
            $stream->update(['publicacion' => $status, 'publicacion_revisada_por' => $request->user()->id,
                'publicacion_revisada_en' => now()]);

            return $previous;
        });
        Audit::record($status === 'aprobada' ? 'grabacion.aprobada' : 'grabacion.retirada', $evento,
            ['antes' => $previous, 'despues' => $status]);

        return $previous;
    }

    private function playablePath(Request $request, Evento $evento): ?string
    {
        $stream = $evento->transmision;
        $path = $stream?->recordingPath();
        if (! $path || $evento->estado !== 'finalizado' || ! $evento->isVisibleTo($request->user())) return null;
        $staff = in_array($request->user()?->role, ['admin', 'operador'], true);

        return $stream->isPublished() || $staff ? $path : null;
    }
}
