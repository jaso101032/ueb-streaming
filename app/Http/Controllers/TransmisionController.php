<?php

namespace App\Http\Controllers;

use App\Models\Evento;
use App\Models\Transmision;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use App\Services\StreamControl;
use Illuminate\Validation\ValidationException;
use App\Support\Audit;

class TransmisionController extends Controller
{
    /**
     * Muestra el panel de control de transmisiones para operadores/admins.
     */
    public function index()
    {
        // This is an OBS workspace, not an archive or the scheduled playlist.
        // Finished events belong to "Gestionar eventos" and scheduled videos
        // belong to "Parrilla 24/7", keeping one clear purpose per screen.
        $eventos = Evento::with(['transmision', 'organizador', 'facultad', 'carrera', 'categoria'])
            ->whereNotIn('estado', ['finalizado', 'cancelado'])
            ->whereDoesntHave('scheduledVideo')
            ->orderBy('fecha_inicio')
            ->paginate(10);

        return view('streaming.index', compact('eventos'));
    }

    /**
     * Genera o reinicia la clave de transmisión (Stream Key) para un evento.
     */
    public function generarClave(Request $request, Evento $evento)
    {
        abort_if($evento->scheduledVideo, 409, 'Una emisión pregrabada no utiliza OBS.');
        abort_if(in_array($evento->estado, ['finalizado', 'cancelado']), 422, 'Crea otro evento para una nueva transmisión.');
        abort_if($evento->transmision?->iniciado_en, 422, 'La clave no puede cambiar después de comenzar la grabación. Crea otro evento.');
        // Generar una clave única y segura para RTMP
        $streamKey = 'ueb_' . Str::random(32);
        $host = $request->getHost();
        $rtmpServer = config('streaming.rtmp_url') ?: 'rtmp://' . $host . ':1935/live';

        // Actualizar o crear la transmisión asociada al evento
        $stream = Transmision::updateOrCreate(
            ['evento_id' => $evento->id],
            [
                'stream_key' => $streamKey,
                'rtmp_server' => $rtmpServer,
                'hls_url' => null,
                'activo' => false,
            ]
        );
        $stream->update(['hls_url' => $stream->playbackUrl()]);
        Audit::record('transmision.clave_generada', $evento);

        return redirect()->back()->with('success', 'Clave de transmisión generada exitosamente.');
    }

    public function finalizar(Evento $evento, StreamControl $control)
    {
        try {
            DB::transaction(function () use ($evento, $control) {
                $locked = Evento::whereKey($evento->id)->lockForUpdate()->firstOrFail();
                $stream = $locked->transmision()->lockForUpdate()->first();
                if ($locked->estado === 'finalizado' && $stream?->finalizado_en) return;
                if (!$stream || $locked->estado === 'cancelado') {
                    throw ValidationException::withMessages(['streaming' => 'Este evento no tiene una transmisión que se pueda finalizar.']);
                }

                $control->stop($stream->stream_key);
                app(\App\Services\ChannelClock::class)->stop($stream);
                $stream->update(['activo' => false, 'finalizado_en' => now(), 'espectadores_activos' => 0]);
                $locked->update(['estado' => 'finalizado']);
            });
        } catch (\Illuminate\Http\Client\ConnectionException | \RuntimeException $exception) {
            return back()->withErrors(['streaming' => 'No se pudo confirmar el cierre en el servidor. Revisa la configuración de streaming e inténtalo de nuevo. El evento no se marcó como finalizado.']);
        }

        Audit::record('transmision.finalizada', $evento);

        return back()->with('success', 'Transmisión finalizada. La señal se cerró y OBS no podrá reconectar este evento.');
    }

    public function estado(Evento $evento)
    {
        $transmision = $evento->transmision;
        // Nginx escribe el playlist en /tmp/hls; solo se considera activo si se actualizo recientemente.
        $playlist = $transmision?->stream_key
            ? '/tmp/hls/' . $transmision->stream_key . '.m3u8'
            : null;
        $enVivo = !in_array($evento->estado, ['finalizado', 'cancelado']) && !$transmision?->finalizado_en && $playlist && is_file($playlist) && filemtime($playlist) >= now()->subSeconds(15)->timestamp;

        return response()->json([
            'en_vivo' => (bool) $enVivo,
            'finalizado' => $evento->estado === 'finalizado',
            'espectadores' => $transmision?->espectadores_activos ?: 0,
            'actualizado' => now()->toIso8601String(),
        ]);
    }

    /**
     * Registra una presencia anónima y temporal en una sala pública.
     * El navegador envía un UUID local: no se guardan cuentas, nombres ni direcciones IP.
     */
    public function viewerHeartbeat(Request $request, Evento $evento)
    {
        abort_unless($evento->visibleTo($request->user()), 404);
        $data = $request->validate(['viewer' => ['required', 'uuid']]);
        $stream = $evento->transmision;

        if (! $stream || $evento->estado !== 'en_vivo' || $stream->finalizado_en) {
            return response()->json(['viewers' => 0]);
        }

        $now = now();
        DB::table('event_viewers')->updateOrInsert(
            ['evento_id' => $evento->id, 'viewer_key' => $data['viewer']],
            ['last_seen_at' => $now, 'created_at' => $now, 'updated_at' => $now],
        );

        // Old entries are only housekeeping; the count itself uses a short live window.
        DB::table('event_viewers')->where('evento_id', $evento->id)->where('last_seen_at', '<', $now->copy()->subDay())->delete();
        $viewers = DB::table('event_viewers')->where('evento_id', $evento->id)->where('last_seen_at', '>=', $now->copy()->subSeconds(75))->count();
        $stream->update(['espectadores_activos' => $viewers]);

        return response()->json(['viewers' => $viewers]);
    }
}
