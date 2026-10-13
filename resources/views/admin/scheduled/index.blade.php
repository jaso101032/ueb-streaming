<x-app-layout>
    @php
        $scheduledProps = [
            'uploadLimits' => \App\Support\UploadLimits::scheduled(),
            'summary' => $summary, 'channelUrl' => route('channel.index'),
            'action' => route('admin.scheduled.store'), 'csrf' => csrf_token(),
            'progressUrl' => route('admin.scheduled.progress', ['page' => $events->currentPage()]),
            'adAction' => route('admin.ads.store'),
            'ads' => $ads->map(fn ($ad) => [
                'id' => $ad->id, 'title' => $ad->title, 'duration' => $ad->duration,
                'status' => $ad->processing_status, 'active' => $ad->is_active,
                'progress' => $ad->processing_progress,
                'error' => $ad->processing_error,
                'toggleUrl' => route('admin.ads.toggle', $ad),
                'retryUrl' => route('admin.ads.retry', $ad),
            ])->values(),
            'timezone' => config('app.timezone'), 'categories' => $categories->map->only(['id', 'nombre'])->values(),
            'old' => session()->getOldInput(), 'errors' => $errors->all(), 'success' => session('success'),
            'events' => $events->map(fn ($event) => [
                'id' => $event->id, 'title' => $event->titulo,
                'day' => $event->fecha_inicio->format('d'), 'month' => $event->fecha_inicio->format('m / Y'),
                'time' => $event->fecha_inicio->format('H:i'), 'minutes' => ceil($event->scheduledVideo->duration / 60),
                'estimatedStart' => $timeline[$event->id]['starts_at'] ?? null,
                'state' => $event->scheduledVideo->processing_status !== 'ready' ? $event->scheduledVideo->processing_status : ($timeline[$event->id]['state'] ?? 'cancelled'),
                'progress' => $event->scheduledVideo->processing_progress,
                'error' => $event->scheduledVideo->processing_error,
                'retryUrl' => route('admin.scheduled.retry', $event),
                'sourceBytes' => $event->scheduledVideo->source_bytes, 'optimizedBytes' => $event->scheduledVideo->optimized_bytes, 'url' => route('eventos.show', $event),
            ])->values(),
            'pagination' => ['total' => $events->total(), 'current' => $events->currentPage(), 'last' => $events->lastPage(),
                'previous' => $events->previousPageUrl(), 'next' => $events->nextPageUrl()],
        ];
    @endphp
    <div id="uebtv-scheduled-studio" data-props="{{ json_encode($scheduledProps) }}"></div>
    <noscript><p class="studio-page">Activa JavaScript para gestionar el contenido programado.</p></noscript>
</x-app-layout>
