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
        // Un video del catálogo se reproduce como cualquier video a demanda.
        $catalogVideo = $scheduled?->isCatalogOnly() ? $scheduled : null;
        if ($catalogVideo) $scheduled = null;
        $uploaded = $scheduled ?? $catalogVideo;
        $recording = $e->transmision;
        $staff = in_array(auth()->user()?->role, ['admin', 'operador'], true);
        $data = ['id' => $e->id, 'title' => $e->titulo, 'description' => $e->descripcion, 'status' => $e->estado,
            'category' => $e->categoria?->nombre ?? 'Institucional', 'faculty' => $e->facultad?->nombre ?? 'Toda la institución',
            'career' => $e->carrera?->nombre ?? 'Sin carrera específica', 'organizer' => $e->organizador?->name ?? 'UEB',
            'type' => $e->tipo_evento, 'place' => $e->lugar, 'date' => $e->fecha_inicio?->format('d/m/Y · H:i'),
            'image' => $e->miniatura ? asset('storage/'.$e->miniatura) : null, 'url' => route('eventos.show', $e),
            'scheduled' => $scheduled ? ['stateUrl' => route('scheduled.state', $e), 'mediaUrl' => route('scheduled.media', $e), 'state' => $scheduled->playback()['state'],
                'adaptiveUrl' => self::adaptiveUrl($e, $scheduled)] : null,
            'videoUrl' => $catalogVideo ? ($catalogVideo->processing_status === 'ready' ? route('scheduled.media', $e) : null)
                : (($recordingPlayable = $recording?->recordingPath() && $e->estado === 'finalizado' && ($recording->isPublished() || $staff)) ? route('eventos.recording', $e) : $e->video_url),
            'adaptiveUrl' => $catalogVideo ? self::adaptiveUrl($e, $catalogVideo) : (! $scheduled && ($recordingPlayable ?? false) ? self::adaptiveUrl($e, $recording) : null),
            'catalogVideo' => $catalogVideo ? ['status' => $catalogVideo->processing_status, 'progress' => $catalogVideo->processing_progress] : null,
            'visibility' => $e->visibilidad ?? 'publico',
            'license' => Evento::LICENSES[$e->licencia ?? 'institucional']['short'] ?? Evento::LICENSES['institucional']['short'],
            'subtitlesUrl' => (($scheduled ?? $catalogVideo) ? ($scheduled ?? $catalogVideo)->subtitles_path : $recording?->subtitles_path) ? route('eventos.subtitles', $e, false) : null,
            // Estas consultas deben mantener el mismo origen desde el que se
            // abrió la sala. Así funcionan tanto en 127.0.0.1 como desde un
            // teléfono conectado mediante el proxy local del puerto 8080.
            'recordingStatus' => $recording?->recording_status, 'viewers' => $e->estado === 'en_vivo' ? app(\App\Services\ViewerPresence::class)->count($e) : ($recording?->espectadores_activos ?? 0), 'viewersUrl' => '/eventos/'.$e->id.'/espectadores', 'liveSince' => $e->estado === 'en_vivo' ? $recording?->iniciado_en?->toIso8601String() : null, 'videoViewsUrl' => route('eventos.video-views', $e), 'playbackUrl' => '/eventos/'.$e->id.'/estado',
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
            $data += ['editUrl' => $canEdit && ! $e->scheduledVideo ? route('admin.eventos.edit', $e) : null, 'detailUrl' => $canEdit ? route('admin.eventos.show', $e) : null,
                'deleteUrl' => auth()->user()?->can('delete-event') ? route('admin.eventos.destroy', $e) : null,
                'finishUrl' => $canStream && $recording && ! $recording->finalizado_en && ! in_array($e->estado, ['finalizado', 'cancelado']) ? route('streaming.finalizar', $e) : null,
                'retryUrl' => auth()->user()?->role === 'admin' && $recording?->recording_status === 'failed' ? route('streaming.recording.retry', $e) : null,
                'downloadUrl' => auth()->user()?->role === 'admin' && \App\Http\Controllers\Admin\MediaDownloadController::eventFile($e) ? route('admin.media.download', $e->id) : null,
                'generateUrl' => $canStream && ! $e->scheduledVideo && ! $recording?->iniciado_en && ! in_array($e->estado, ['finalizado', 'cancelado']) ? route('streaming.generar.clave', $e) : null,
                'streamKey' => $canStream ? $recording?->stream_key : null, 'server' => $canStream ? (config('streaming.rtmp_url') ?: 'rtmp://'.request()->getHost().':1935/live') : null, 'statusUrl' => $canStream ? route('streaming.estado', $e) : null];
            if (auth()->user()?->role === 'admin') {
                $ready = (bool) $recording?->recordingPath();
                $data['usage'] = ['license' => Evento::LICENSES[$e->licencia ?? 'institucional']['name'] ?? $e->licencia, 'authorization' => $e->autorizacion_referencia];
                $data['broadcastAuthorization'] = ['status' => $e->authorizationStatus(), 'responsable' => $e->autorizacion_responsable,
                    'referencia' => $e->autorizacion_referencia, 'alcance' => $e->autorizacion_alcance,
                    'fecha' => $e->autorizacion_fecha?->format('Y-m-d'), 'vigencia' => $e->autorizacion_vigencia?->format('Y-m-d'),
                    'updateUrl' => route('admin.authorization.update', $e), 'revokeUrl' => $e->autorizacion_fecha ? route('admin.authorization.destroy', $e) : null];
                $data['publication'] = $recording ? ['status' => $recording->publicacion,
                    'reviewedBy' => $recording->revisor?->name, 'reviewedAt' => $recording->publicacion_revisada_en?->format('d/m/Y · H:i'),
                    'approveUrl' => $ready && $recording->publicacion !== 'aprobada' ? route('admin.recording.approve', $e) : null,
                    'withdrawUrl' => $ready && $recording->publicacion === 'aprobada' ? route('admin.recording.withdraw', $e) : null] : null;
                $media = $uploaded ? ($uploaded->processing_status === 'ready' ? $uploaded : null) : ($recording?->recordingPath() ? $recording : null);
                $data['subtitles'] = $media ? \App\Support\Subtitles::adminState($media, ['uploadUrl' => route('admin.subtitles.store', $e),
                    'removeUrl' => route('admin.subtitles.destroy', $e), 'generateUrl' => route('admin.subtitles.generate', $e),
                    'previewUrl' => route('eventos.subtitles', $e, false)]) : null;
                $stored = $uploaded ? ($uploaded->processing_status === 'ready' ? $uploaded : null) : ($recording?->recordingPath() ? $recording : null);
                $data['renditions'] = $stored && config('streaming.renditions.enabled') ? ['status' => $stored->renditions_status ?? 'pending',
                    'progress' => $stored->renditions_progress, 'error' => $stored->renditions_error,
                    'levels' => collect($stored->renditions_levels ?? [])->pluck('name')->values()] : null;
                $data['recordingMetadata'] = $recording?->displayRecordingMetadata();
                // Vista previa para el equipo (también si la grabación aún no está publicada).
                $data['previewUrl'] = $uploaded
                    ? ($uploaded->processing_status === 'ready' ? route('admin.media.scheduled-preview', $e) : null)
                    : ($recordingPlayable ? route('eventos.recording', $e) : null);
                $data['scheduledFile'] = $uploaded ? ['duration' => $uploaded->duration, 'bytes' => $uploaded->optimized_bytes,
                    'status' => $uploaded->processing_status, 'progress' => $uploaded->processing_progress, 'error' => $uploaded->processing_error] : null;
                $data['recordingFpsLimit'] = $recording?->recording_fps_limit;
            }
        }

        return $data;
    }

    /** Lista maestra HLS (mismo origen) si las calidades adaptativas están listas. */
    private static function adaptiveUrl(Evento $e, $media): ?string
    {
        return config('streaming.renditions.enabled') && $media?->renditions_status === 'ready'
            && \App\Services\AdaptiveRenditions::validDirectory($media->renditions_path)
            ? route('eventos.renditions', [$e, 'master.m3u8'], false) : null;
    }

    /** Opciones para clasificar un video: categorías, facultades y carreras. */
    public static function videoOptions(): array
    {
        return ['categories' => \App\Models\Categoria::orderBy('nombre')->get(['id', 'nombre']),
            'faculties' => \App\Models\Facultad::orderBy('nombre')->get(['id', 'nombre']),
            'careers' => \App\Models\Carrera::orderBy('nombre')->get(['id', 'nombre', 'facultad_id'])];
    }

    /** Datos para editar un video subido (parrilla o catálogo). */
    public static function videoEdit(Evento $e): ?array
    {
        $video = $e->scheduledVideo;
        if (! $video || auth()->user()?->role !== 'admin') return null;

        return ['action' => route('admin.media.videos.update', $e), 'mode' => $video->isCatalogOnly() ? 'catalog' : 'channel',
            'values' => ['titulo' => $e->titulo, 'descripcion' => $e->descripcion, 'categoria_id' => $e->categoria_id,
                'facultad_id' => $e->facultad_id, 'carrera_id' => $e->carrera_id, 'visibilidad' => $e->visibilidad ?? 'publico',
                'image' => $e->miniatura ? asset('storage/'.$e->miniatura) : null]] + self::videoOptions();
    }

    public static function shell(): array
    {
        $user = auth()->user();
        $admin = $user?->role === 'admin';
        $operator = in_array($user?->role, ['admin', 'operador'], true);
        $canEvents = (bool) $user?->allowsOperatorAction('events');
        $canStream = (bool) $user?->allowsOperatorAction('stream');
        // Menú agrupado: Transmitir (lo del día a día), Contenido y Administración.
        // El catálogo público ya no está aquí: es la vista de la comunidad y va
        // abajo como "Ver sitio público".
        $links = [];
        $link = fn ($group, $label, $route, $pattern, $icon) => ['group' => $group, 'label' => $label, 'url' => route($route),
            'active' => request()->routeIs($pattern) && ! request()->routeIs('admin.eventos.create'), 'icon' => $icon];
        if ($admin) $links[] = $link('Transmitir', 'Resumen', 'admin.dashboard', 'admin.dashboard', 0);
        if ($operator && $canEvents) $links[] = $link('Transmitir', 'Eventos', 'admin.eventos.index', 'admin.eventos.*', 1);
        if ($operator && $canStream) $links[] = $link('Transmitir', 'Emitir con OBS', 'streaming.control', 'streaming.*', 2);
        if ($admin) {
            $links[] = $link('Transmitir', 'Parrilla 24/7', 'admin.scheduled.index', 'admin.scheduled.*', 4);
            $links[] = $link('Contenido', 'Biblioteca de medios', 'admin.media.index', 'admin.media.*', 7);
            $links[] = $link('Contenido', 'Marca de agua', 'admin.watermark.edit', 'admin.watermark.*', 5);
            $links[] = $link('Administración', 'Usuarios', 'admin.usuarios.index', 'admin.usuarios.*', 6);
            $links[] = $link('Administración', 'Auditoría', 'admin.audit.index', 'admin.audit.*', 8);
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
