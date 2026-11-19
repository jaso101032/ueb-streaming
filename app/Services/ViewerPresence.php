<?php

namespace App\Services;

use App\Models\Evento;
use Illuminate\Support\Facades\DB;

/**
 * Presencia anónima de espectadores en un directo.
 *
 * Cada navegador envía una señal periódica con un identificador propio. Se
 * considera conectado a quien envió señal en los últimos WINDOW segundos, de
 * modo que el número baja solo cuando alguien cierra la página, sin depender
 * de que otra persona envíe una señal después.
 */
class ViewerPresence
{
    /** Segundos sin señal tras los cuales alguien deja de contarse (3-4 señales perdidas). */
    public const WINDOW = 75;

    /** Registra la señal de un navegador y devuelve cuántos hay conectados. */
    public function touch(Evento $evento, string $viewer): int
    {
        $now = now();
        DB::table('event_viewers')->updateOrInsert(
            ['evento_id' => $evento->id, 'viewer_key' => $viewer],
            ['last_seen_at' => $now, 'created_at' => $now, 'updated_at' => $now],
        );

        // Las filas viejas solo son limpieza; el conteo usa la ventana corta.
        DB::table('event_viewers')->where('evento_id', $evento->id)
            ->where('last_seen_at', '<', $now->copy()->subDay())->delete();

        return $this->remember($evento);
    }

    /** Conectados ahora mismo. Se calcula al leer, nunca se confía en el último valor guardado. */
    public function count(Evento $evento): int
    {
        return DB::table('event_viewers')->where('evento_id', $evento->id)
            ->where('last_seen_at', '>=', now()->subSeconds(self::WINDOW))->count();
    }

    /** Guarda el conteo en la transmisión, para los paneles que lo leen de ahí. */
    public function remember(Evento $evento): int
    {
        $viewers = $this->count($evento);
        $evento->transmision?->update(['espectadores_activos' => $viewers]);

        return $viewers;
    }
}
