<x-app-layout>
    <div class="studio-page">
        <div class="studio-heading"><div><p class="studio-eyebrow">TU PROGRAMACIÓN</p><h1>Eventos<span class="studio-heading-dot">.</span></h1><p>Organiza los encuentros que conectan a nuestra comunidad.</p></div><a href="{{ route('admin.eventos.create') }}" class="studio-button"><span aria-hidden="true">＋</span> Crear evento</a></div>
        @if(session('success'))<div class="studio-alert" role="status">{{ session('success') }}</div>@endif
        <section class="studio-panel studio-management">
            <div class="studio-panel-heading"><div><h2>Todos los eventos</h2><p>{{ $eventos->total() }} eventos en tu programación</p></div><a class="studio-text-link" href="{{ route('eventos.index') }}">Ver catálogo público ↗</a></div>
            @forelse($eventos as $evento)
                <article class="studio-management-row">
                    <div class="studio-event-thumb">@if($evento->miniatura)<img src="{{ asset('storage/'.$evento->miniatura) }}" alt="" loading="lazy">@else<span aria-hidden="true">▶</span>@endif</div>
                    <div class="studio-event-info"><small>{{ $evento->categoria?->nombre ?? $evento->tipo_evento }}</small><h3><a href="{{ route('admin.eventos.show', $evento) }}">{{ $evento->titulo }}</a></h3><p>{{ $evento->fecha_inicio?->format('d/m/Y · H:i') }} · {{ $evento->facultad?->siglas ?? 'UEB' }}</p></div>
                    <span class="studio-status studio-status-{{ $evento->estado }}">{{ ucfirst(str_replace('_', ' ', $evento->estado)) }}</span>
                    <div class="studio-row-actions"><a href="{{ route('admin.eventos.show', $evento) }}" aria-label="Ver {{ $evento->titulo }}">Ver</a><a href="{{ route('admin.eventos.edit', $evento) }}" aria-label="Editar {{ $evento->titulo }}">Editar</a><form action="{{ route('admin.eventos.destroy', $evento) }}" method="POST">@csrf @method('DELETE')<button type="submit" class="studio-delete" aria-label="Eliminar {{ $evento->titulo }}" onclick="return confirm('¿Estás seguro de eliminar este evento?')">Eliminar</button></form></div>
                </article>
            @empty
                <div class="studio-empty"><span class="studio-empty-icon" aria-hidden="true">＋</span><h3>Hagamos espacio para tu próximo evento</h3><p>Agrega un título, una fecha y una miniatura. Tu programación empieza aquí.</p><a class="studio-button" href="{{ route('admin.eventos.create') }}">Crear mi primer evento ↗</a></div>
            @endforelse
            @if($eventos->hasPages())<div class="studio-pagination">{{ $eventos->links() }}</div>@endif
        </section>
    </div>
</x-app-layout>
