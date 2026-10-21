<?php

namespace App\Services;

use App\Jobs\FanoutAudienceAnnouncement;
use App\Models\Evento;
use Illuminate\Support\Facades\DB;

class AudienceAnnouncements
{
    public function publish(Evento $event, string $kind): void
    {
        if (! in_array($kind, ['live', 'video'], true) || $event->estado === 'cancelado') return;

        $title = $kind === 'live' ? 'UEBTV está en vivo: '.$event->titulo : 'Nuevo video: '.$event->titulo;
        $inserted = DB::table('audience_announcements')->insertOrIgnore([
            'evento_id' => $event->id,
            'kind' => $kind,
            'title' => mb_substr($title, 0, 250),
            'body' => $kind === 'live' ? 'La transmisión ya comenzó. Únete a la señal.' : 'Ya puedes ver este contenido en UEBTV.',
            'url' => '/eventos/'.$event->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        if (! $inserted) return;

        $id = DB::table('audience_announcements')->where('evento_id', $event->id)->where('kind', $kind)->value('id');
        FanoutAudienceAnnouncement::dispatch($id)->afterCommit()->delay($kind === 'live' ? now()->addSeconds(10) : now());
    }
}
