<x-app-layout>
    <div class="uebtv-admin-page">
        <section class="uebtv-admin-intro">
            <div><p class="uebtv-kicker uebtv-kicker--navy"><span></span> Centro de operaciones</p><h1>Vista general</h1><p>Monitorea la programación y la señal institucional desde un solo lugar.</p></div>
            <div class="uebtv-admin-intro__meta"><span><i></i> Sistema operativo</span><small>{{ now()->format('d M Y') }}</small></div>
        </section>

        <section class="uebtv-admin-metrics">
            <article><span class="uebtv-metric-icon">◎</span><span class="uebtv-metric-label">Comunidad</span><strong>{{ $totalUsuarios }}</strong><small>usuarios registrados</small></article>
            <article><span class="uebtv-metric-icon">□</span><span class="uebtv-metric-label">Programación</span><strong>{{ $totalEventos }}</strong><small>eventos registrados</small></article>
            <article class="uebtv-metric-live"><span class="uebtv-metric-icon">●</span><span class="uebtv-metric-label">En directo</span><strong>{{ $eventosEnVivo }}</strong><small>eventos transmitiendo</small><i></i></article>
            <article><span class="uebtv-metric-icon">◌</span><span class="uebtv-metric-label">Señales</span><strong>{{ $transmisionesActivas }}</strong><small>streams configurados</small></article>
        </section>

        <div class="uebtv-admin-lower">
        <section class="uebtv-admin-table">
            <div class="uebtv-admin-table__head"><div><p class="uebtv-kicker uebtv-kicker--navy"><span></span> Actividad reciente</p><h2>Últimos eventos</h2></div><a href="{{ route('admin.eventos.index') }}">Gestionar eventos <span>↗</span></a></div>
            <div class="overflow-x-auto"><table><thead><tr><th>Evento</th><th>Facultad</th><th>Estado</th><th>Organizador</th><th></th></tr></thead><tbody>
                @forelse($eventosRecientes as $evento)
                    <tr><td><strong>{{ $evento->titulo }}</strong><small>{{ $evento->categoria->nombre ?? 'Programación institucional' }}</small></td><td>{{ $evento->facultad->siglas ?? 'UEB' }}</td><td><span class="uebtv-status {{ $evento->estado === 'en_vivo' ? 'uebtv-status--live' : '' }}"><i></i>{{ str_replace('_', ' ', ucfirst($evento->estado)) }}</span></td><td>{{ $evento->organizador->name ?? 'N/D' }}</td><td><a href="{{ route('eventos.show', $evento) }}">Ver detalle <span>↗</span></a></td></tr>
                @empty
                    <tr><td colspan="5" class="uebtv-empty">No hay eventos registrados en el sistema.</td></tr>
                @endforelse
            </tbody></table></div>
        </section>
        <aside class="uebtv-admin-quick"><p class="uebtv-kicker uebtv-kicker--navy"><span></span> Accesos rápidos</p><h2>¿Qué quieres hacer?</h2><a href="{{ route('admin.eventos.create') }}"><span class="uebtv-quick-icon">＋</span><span><strong>Crear evento</strong><small>Programa una nueva transmisión</small></span><b>↗</b></a><a href="{{ route('streaming.control') }}"><span class="uebtv-quick-icon">◉</span><span><strong>Control de streaming</strong><small>Configura las credenciales de OBS</small></span><b>↗</b></a><a href="{{ route('eventos.index') }}"><span class="uebtv-quick-icon">▶</span><span><strong>Ver catálogo público</strong><small>Comprueba la experiencia UEBTV</small></span><b>↗</b></a></aside>
        </div>
    </div>
</x-app-layout>