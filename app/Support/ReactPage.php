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
            'recordingStatus' => $recording?->recording_status, 'playbackUrl' => route('eventos.playback-state', $e),
            'hlsUrl' => $recording ? 'http://'.request()->getHost().'/hls/'.$recording->stream_key.'.m3u8' : null,
            'chatUrl' => route('eventos.chat.index', $e)];
        if ($manage) {
            $data += ['editUrl' => $scheduled ? null : route('admin.eventos.edit', $e), 'detailUrl' => route('admin.eventos.show', $e),
                'deleteUrl' => auth()->user()?->can('delete-event') ? route('admin.eventos.destroy', $e) : null,
                'finishUrl' => $recording && ! $recording->finalizado_en && ! in_array($e->estado, ['finalizado', 'cancelado']) ? route('streaming.finalizar', $e) : null,
                'retryUrl' => $recording?->recording_status === 'failed' ? route('streaming.recording.retry', $e) : null,
                'generateUrl' => ! $scheduled && ! $recording?->iniciado_en && ! in_array($e->estado, ['finalizado', 'cancelado']) ? route('streaming.generar.clave', $e) : null,
                'streamKey' => $recording?->stream_key, 'server' => 'rtmp://'.request()->getHost().':1935/live', 'statusUrl' => route('streaming.estado', $e)];
        }

        return $data;
    }

    public static function shell(): array
    {
        $user = auth()->user();
        $operator = $user?->role === 'admin';
        $links = [];
        if ($operator) {
            foreach (['Vista general' => ['admin.dashboard', 'admin.dashboard'], 'Eventos' => ['admin.eventos.index', 'admin.eventos.*'], 'Streaming' => ['streaming.control', 'streaming.*'], 'Explorar' => ['eventos.index', 'eventos.*']] as $label => $r) {
                $links[] = ['label' => $label, 'url' => route($r[0]), 'active' => request()->routeIs($r[1]) && ! request()->routeIs('admin.eventos.create')];
            }
        }
        if ($user?->role === 'admin') {
            $links[] = ['label' => 'Contenido programado', 'url' => route('admin.scheduled.index'), 'active' => request()->routeIs('admin.scheduled.*')];
        }

        return ['user' => $user?->only(['name', 'email', 'role']), 'links' => $links, 'operator' => $operator, 'createUrl' => $operator ? route('admin.eventos.create') : null,
            'creating' => request()->routeIs('admin.eventos.create'), 'home' => $operator ? route('admin.dashboard') : route('eventos.index'),
            'login' => route('login'), 'register' => route('register'), 'logout' => route('logout'), 'profile' => route('profile.edit'),
            'channel' => route('channel.index'), 'catalog' => route('eventos.index'),
            'logo' => asset('images/brand/ueb-wordmark.png'),
            'mascot' => asset('images/brand/mascota-ueb.webp'),
            'tvLogo' => asset('images/brand/uebtv-logo-transparent.webp'), 'csrf' => csrf_token(),
            'errors' => session('errors')?->all() ?? [], 'success' => session('success'), 'status' => session('status'), 'year' => now()->year];
    }
}
