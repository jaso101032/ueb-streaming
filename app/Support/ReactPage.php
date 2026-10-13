<?php

namespace App\Support;

use App\Models\Evento;

class ReactPage
{
    public static function pagination($items): array
    {
        return ['total' => $items->total(), 'current' => $items->currentPage(), 'last' => $items->lastPage(), 'previous' => $items->previousPageUrl(), 'next' => $items->nextPageUrl()];
    }

    public static function event(Evento $e, bool $manage = false): array
    {
        $scheduled = $e->scheduledVideo;
        $recording = $e->transmision;
        $data = ['id' => $e->id, 'title' => $e->titulo, 'description' => $e->descripcion, 'status' => $e->estado,
            'category' => $e->categoria?->nombre ?? 'Institucional', 'faculty' => $e->facultad?->nombre ?? 'Toda la institución',
            'career' => $e->carrera?->nombre ?? 'Sin carrera específica', 'organizer' => $e->organizador?->name ?? 'UEB',
            'type' => $e->tipo_evento, 'place' => $e->lugar, 'date' => $e->fecha_inicio?->format('d/m/Y · H:i'),
            'image' => $e->miniatura ? asset('storage/'.$e->miniatura) : null, 'url' => route('eventos.show', $e),
            'scheduled' => $scheduled ? ['stateUrl' => route('scheduled.state', $e), 'mediaUrl' => route('scheduled.media', $e), 'state' => $scheduled->playback()['state']] : null,
            'videoUrl' => $recording?->recordingPath() && $e->estado === 'finalizado' ? route('eventos.recording', $e) : $e->video_url,
            // Estas consultas deben mantener el mismo origen desde el que se
            // abrió la sala. Así funcionan tanto en 127.0.0.1 como desde un
            // teléfono conectado mediante el proxy local del puerto 8080.
            'recordingStatus' => $recording?->recording_status, 'viewers' => $recording?->espectadores_activos ?? 0, 'viewersUrl' => '/eventos/'.$e->id.'/espectadores', 'videoViewsUrl' => route('eventos.video-views', $e), 'playbackUrl' => '/eventos/'.$e->id.'/estado',
            // HLS can be served on the same HTTPS domain by the production reverse proxy.
            'hlsUrl' => $recording ? (config('streaming.hls_base_url') ?: request()->getScheme().'://'.request()->getHost().':8080/hls').'/'.$recording->stream_key.'.m3u8' : null,
            'chatUrl' => route('eventos.chat.index', $e)];
        if ($manage) {
            $data += ['editUrl' => $scheduled ? null : route('admin.eventos.edit', $e), 'detailUrl' => route('admin.eventos.show', $e),
                'deleteUrl' => auth()->user()?->can('delete-event') ? route('admin.eventos.destroy', $e) : null,
                'finishUrl' => $recording && ! $recording->finalizado_en && ! in_array($e->estado, ['finalizado', 'cancelado']) ? route('streaming.finalizar', $e) : null,
                'retryUrl' => $recording?->recording_status === 'failed' ? route('streaming.recording.retry', $e) : null,
                'downloadUrl' => auth()->user()?->role === 'admin' && $recording?->recordingPath() ? route('admin.recording.download', $e) : null,
                'generateUrl' => ! $scheduled && ! $recording?->iniciado_en && ! in_array($e->estado, ['finalizado', 'cancelado']) ? route('streaming.generar.clave', $e) : null,
                'streamKey' => $recording?->stream_key, 'server' => config('streaming.rtmp_url') ?: 'rtmp://'.request()->getHost().':1935/live', 'statusUrl' => route('streaming.estado', $e)];
        }

        return $data;
    }

    public static function shell(): array
    {
        $user = auth()->user();
        $operator = $user?->role === 'admin';
        $links = [];
        if ($operator) {
            foreach (['Resumen' => ['admin.dashboard', 'admin.dashboard'], 'Gestionar eventos' => ['admin.eventos.index', 'admin.eventos.*'], 'Emitir con OBS' => ['streaming.control', 'streaming.*'], 'Catálogo público' => ['eventos.index', 'eventos.*']] as $label => $r) {
                $links[] = ['label' => $label, 'url' => route($r[0]), 'active' => request()->routeIs($r[1]) && ! request()->routeIs('admin.eventos.create')];
            }
        }
        if ($user?->role === 'admin') {
            $links[] = ['label' => 'Parrilla 24/7', 'url' => route('admin.scheduled.index'), 'active' => request()->routeIs('admin.scheduled.*')];
        }

        return ['user' => $user?->only(['name', 'email', 'role']), 'links' => $links, 'operator' => $operator, 'createUrl' => $operator ? route('admin.eventos.create') : null,
            // The public logo returns to the continuous channel, the audience's
            // primary entry point. The catalog remains a distinct discovery view.
            'creating' => request()->routeIs('admin.eventos.create'), 'home' => $operator ? route('admin.dashboard') : route('channel.index'),
            'login' => route('login'), 'register' => route('register'), 'logout' => route('logout'), 'profile' => route('profile.edit'),
            'channel' => route('channel.index'), 'catalog' => route('eventos.index'),
            'logo' => asset('images/brand/ueb-wordmark.png'),
            'mascot' => asset('images/brand/mascota-ueb.webp'),
            // UEBTV identifica el producto y la señal; la marca UEB oficial
            // se entrega por separado como firma institucional.
            'tvLogo' => asset('images/brand/uebtv-logo-final.png'),
            'csrf' => csrf_token(),
            'errors' => session('errors')?->all() ?? [], 'success' => session('success'), 'status' => session('status'), 'year' => now()->year];
    }
}
