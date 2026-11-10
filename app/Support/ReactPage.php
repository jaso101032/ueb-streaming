<?php

namespace App\Support;

use App\Models\Evento;
use App\Models\PlayerWatermark;

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
        $staff = in_array(auth()->user()?->role, ['admin', 'operador'], true);
        $data = ['id' => $e->id, 'title' => $e->titulo, 'description' => $e->descripcion, 'status' => $e->estado,
            'category' => $e->categoria?->nombre ?? 'Institucional', 'faculty' => $e->facultad?->nombre ?? 'Toda la institución',
            'career' => $e->carrera?->nombre ?? 'Sin carrera específica', 'organizer' => $e->organizador?->name ?? 'UEB',
            'type' => $e->tipo_evento, 'place' => $e->lugar, 'date' => $e->fecha_inicio?->format('d/m/Y · H:i'),
            'image' => $e->miniatura ? asset('storage/'.$e->miniatura) : null, 'url' => route('eventos.show', $e),
            'scheduled' => $scheduled ? ['stateUrl' => route('scheduled.state', $e), 'mediaUrl' => route('scheduled.media', $e), 'state' => $scheduled->playback()['state']] : null,
            'videoUrl' => $recording?->recordingPath() && $e->estado === 'finalizado' && ($recording->isPublished() || $staff) ? route('eventos.recording', $e) : $e->video_url,
            'visibility' => $e->visibilidad ?? 'publico',
            'subtitlesUrl' => ($scheduled ? $scheduled->subtitles_path : $recording?->subtitles_path) ? route('eventos.subtitles', $e, false) : null,
            // Estas consultas deben mantener el mismo origen desde el que se
            // abrió la sala. Así funcionan tanto en 127.0.0.1 como desde un
            // teléfono conectado mediante el proxy local del puerto 8080.
            'recordingStatus' => $recording?->recording_status, 'viewers' => $recording?->espectadores_activos ?? 0, 'viewersUrl' => '/eventos/'.$e->id.'/espectadores', 'videoViewsUrl' => route('eventos.video-views', $e), 'playbackUrl' => '/eventos/'.$e->id.'/estado',
            // Same-origin public playback id; the OBS key is only shown to operators below.
            'hlsUrl' => $recording?->playbackUrl(),
            'chatUrl' => route('eventos.chat.index', $e), 'chatEnabled' => (bool) $e->chat_enabled,
            'chatRestricted' => (bool) auth()->user()?->chat_restricted,
            'chatToggleUrl' => auth()->user()?->allowsOperatorAction('toggle_chat') && ! $scheduled && ! in_array($e->estado, ['finalizado', 'cancelado']) ? route('streaming.chat.toggle', $e) : null];
        if (auth()->user()?->role === 'admin') {
            $data['analyticsUrl'] = route('admin.eventos.analytics', $e);
            if (array_key_exists('catalog_views', $e->getAttributes())) {
                $data['viewCount'] = (int) $e->getAttribute('catalog_views');
            }
        }
        if ($manage) {
            $canEdit = (bool) auth()->user()?->allowsOperatorAction('events');
            $canStream = (bool) auth()->user()?->allowsOperatorAction('stream');
            $data += ['editUrl' => $canEdit && ! $scheduled ? route('admin.eventos.edit', $e) : null, 'detailUrl' => $canEdit ? route('admin.eventos.show', $e) : null,
                'deleteUrl' => auth()->user()?->can('delete-event') ? route('admin.eventos.destroy', $e) : null,
                'finishUrl' => $canStream && $recording && ! $recording->finalizado_en && ! in_array($e->estado, ['finalizado', 'cancelado']) ? route('streaming.finalizar', $e) : null,
                'retryUrl' => auth()->user()?->role === 'admin' && $recording?->recording_status === 'failed' ? route('streaming.recording.retry', $e) : null,
                'downloadUrl' => auth()->user()?->role === 'admin' && $recording?->recordingPath() ? route('admin.recording.download', $e) : null,
                'generateUrl' => $canStream && ! $scheduled && ! $recording?->iniciado_en && ! in_array($e->estado, ['finalizado', 'cancelado']) ? route('streaming.generar.clave', $e) : null,
                'streamKey' => $canStream ? $recording?->stream_key : null, 'server' => $canStream ? (config('streaming.rtmp_url') ?: 'rtmp://'.request()->getHost().':1935/live') : null, 'statusUrl' => $canStream ? route('streaming.estado', $e) : null];
            if (auth()->user()?->role === 'admin') {
                $ready = (bool) $recording?->recordingPath();
                $data['publication'] = $recording ? ['status' => $recording->publicacion,
                    'reviewedBy' => $recording->revisor?->name, 'reviewedAt' => $recording->publicacion_revisada_en?->format('d/m/Y · H:i'),
                    'approveUrl' => $ready && $recording->publicacion !== 'aprobada' ? route('admin.recording.approve', $e) : null,
                    'withdrawUrl' => $ready && $recording->publicacion === 'aprobada' ? route('admin.recording.withdraw', $e) : null] : null;
                $media = $scheduled ? ($scheduled->processing_status === 'ready' ? $scheduled : null) : ($recording?->recordingPath() ? $recording : null);
                $data['subtitles'] = $media ? ['present' => (bool) $media->subtitles_path, 'uploadUrl' => route('admin.subtitles.store', $e),
                    'removeUrl' => $media->subtitles_path ? route('admin.subtitles.destroy', $e) : null] : null;
                $data['recordingMetadata'] = $recording?->displayRecordingMetadata();
                $data['recordingFpsLimit'] = $recording?->recording_fps_limit;
            }
        }

        return $data;
    }

    public static function shell(): array
    {
        $user = auth()->user();
        $admin = $user?->role === 'admin';
        $operator = in_array($user?->role, ['admin', 'operador'], true);
        $canEvents = (bool) $user?->allowsOperatorAction('events');
        $canStream = (bool) $user?->allowsOperatorAction('stream');
        $links = [];
        if ($admin) {
            $links[] = ['label' => 'Resumen', 'url' => route('admin.dashboard'), 'active' => request()->routeIs('admin.dashboard'), 'icon' => 0];
        }
        if ($operator) {
            foreach ([
                ['Gestionar eventos', 'admin.eventos.index', 'admin.eventos.*', 1],
                ['Emitir con OBS', 'streaming.control', 'streaming.*', 2],
                ['Catálogo público', 'eventos.index', 'eventos.*', 3],
            ] as [$label, $url, $pattern, $icon]) {
                if ($label === 'Gestionar eventos' && ! $canEvents) continue;
                if ($label === 'Emitir con OBS' && ! $canStream) continue;
                $links[] = ['label' => $label, 'url' => route($url), 'active' => request()->routeIs($pattern) && ! request()->routeIs('admin.eventos.create'), 'icon' => $icon];
            }
        }
        if ($admin) {
            $links[] = ['label' => 'Biblioteca de medios', 'url' => route('admin.media.index'), 'active' => request()->routeIs('admin.media.*'), 'icon' => 3];
            $links[] = ['label' => 'Parrilla 24/7', 'url' => route('admin.scheduled.index'), 'active' => request()->routeIs('admin.scheduled.*'), 'icon' => 4];
            $links[] = ['label' => 'Marca de agua', 'url' => route('admin.watermark.edit'), 'active' => request()->routeIs('admin.watermark.*'), 'icon' => 5];
            $links[] = ['label' => 'Gestionar usuarios', 'url' => route('admin.usuarios.index'), 'active' => request()->routeIs('admin.usuarios.*'), 'icon' => 6];
            $links[] = ['label' => 'Auditoría', 'url' => route('admin.audit.index'), 'active' => request()->routeIs('admin.audit.*'), 'icon' => 6];
        }

        return ['user' => $user?->only(['name', 'email', 'role']), 'links' => $links, 'operator' => $operator, 'panelLabel' => $admin ? 'CENTRO DE PRODUCCIÓN' : 'CENTRO DE OPERACIÓN', 'createUrl' => $canEvents ? route('admin.eventos.create') : null,
            // The public logo returns to the continuous channel, the audience's
            // primary entry point. The catalog remains a distinct discovery view.
            'creating' => request()->routeIs('admin.eventos.create'), 'home' => $admin ? route('admin.dashboard') : ($canStream ? route('streaming.control') : ($canEvents ? route('admin.eventos.index') : route('channel.index'))),
            'login' => route('login'), 'register' => route('register'), 'logout' => route('logout'), 'profile' => route('profile.edit'),
            'channel' => route('channel.index'), 'catalog' => route('eventos.index'),
            'notifications' => ['index' => route('notifications.index'), 'preferences' => route('notifications.preferences'),
                'readBase' => url('/mis-notificaciones'), 'push' => route('notifications.push.subscribe')],
            'logo' => asset('images/brand/ueb-wordmark.png'),
            'headerLogo' => asset('images/brand/facagei-logo-light.png'),
            'storage' => $admin ? StorageUsage::recordings() : null,
            'mascot' => asset('images/brand/mascota-ueb.webp'),
            // UEBTV identifica el producto y la señal; la marca UEB oficial
            // se entrega por separado como firma institucional.
            'tvLogo' => asset('images/brand/uebtv-logo-final.png'),
            'playerWatermark' => PlayerWatermark::current(),
            'csrf' => csrf_token(),
            'errors' => session('errors')?->all() ?? [], 'success' => session('success'), 'status' => session('status'), 'year' => now()->year];
    }
}
