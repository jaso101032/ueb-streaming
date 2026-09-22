<x-app-layout>
    @php
        $studioProps = [
            'name' => auth()->user()->name,
            'metrics' => [
                ['label' => 'Eventos creados', 'value' => $totalEventos, 'detail' => 'Tu programación institucional', 'icon' => 'calendar'],
                ['label' => 'En vivo', 'value' => $eventosEnVivo, 'detail' => 'Eventos marcados en directo', 'icon' => 'signal'],
                ['label' => 'Comunidad', 'value' => $totalUsuarios, 'detail' => 'Usuarios registrados', 'icon' => 'users'],
                ['label' => 'Transmisiones', 'value' => $transmisionesActivas, 'detail' => 'Configuraciones de streaming', 'icon' => 'play'],
            ],
            'events' => $eventosRecientes->map(fn ($event) => [
                'id' => $event->id, 'title' => $event->titulo, 'status' => $event->estado,
                'category' => $event->categoria?->nombre ?? 'Institucional',
                'date' => $event->fecha_inicio?->format('d/m/Y · H:i'),
                'image' => $event->miniatura ? asset('storage/'.$event->miniatura) : null,
                'url' => route('admin.eventos.show', $event),
            ])->values(),
            'createUrl' => route('admin.eventos.create'), 'eventsUrl' => route('admin.eventos.index'),
            'streamingUrl' => route('streaming.control'), 'catalogUrl' => route('eventos.index'),
        ];
    @endphp
    <div id="uebtv-admin-studio" data-props="{{ json_encode($studioProps) }}"></div>
    <noscript><div class="studio-page"><h1>Administración UEBTV</h1><p>Activa JavaScript para ver el resumen interactivo.</p><a href="{{ route('admin.eventos.index') }}">Gestionar eventos</a> · <a href="{{ route('admin.eventos.create') }}">Crear evento</a></div></noscript>
</x-app-layout>
