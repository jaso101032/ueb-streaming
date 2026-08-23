<?php

namespace App\Http\Controllers;

use App\Models\Evento;
use App\Models\Transmision;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TransmisionController extends Controller
{
    /**
     * Muestra el panel de control de transmisiones para operadores/admins.
     */
    public function index()
    {
        $eventos = Evento::with(['transmision', 'organizador', 'facultad'])
            ->orderBy('fecha_inicio', 'desc')
            ->paginate(10);

        return view('streaming.index', compact('eventos'));
    }

    /**
     * Genera o reinicia la clave de transmisión (Stream Key) para un evento.
     */
    public function generarClave(Request $request, Evento $evento)
    {
        // Generar una clave única y segura para RTMP
        $streamKey = 'ueb_' . Str::random(32);
        $host = $request->getHost();
        $rtmpServer = 'rtmp://' . $host . ':1935/live';
        $hlsUrl = 'http://' . $host . '/hls/' . $streamKey . '.m3u8';

        // Actualizar o crear la transmisión asociada al evento
        Transmision::updateOrCreate(
            ['evento_id' => $evento->id],
            [
                'stream_key' => $streamKey,
                'rtmp_server' => $rtmpServer,
                'hls_url' => $hlsUrl,
                'activo' => false,
            ]
        );

        return redirect()->back()->with('success', 'Clave de transmisión generada exitosamente.');
    }
}