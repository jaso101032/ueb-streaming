<x-app-layout>
    <main class="uebtv-catalog-page">
        <div class="uebtv-catalog-container">
            <section class="uebtv-catalog-hero">
                <div><p class="uebtv-kicker uebtv-kicker--navy"><span></span> Programación institucional</p><h1>Todo lo que pasa<br><em>en la UEB.</em></h1><p>Transmisiones, conferencias y contenidos para seguir conectados con nuestra comunidad.</p></div>
                <div class="uebtv-catalog-mark" aria-hidden="true"><strong>UEB</strong><span>TV</span><i></i></div>
            </section>
            <div class="uebtv-catalog-toolbar"><div><h2>Eventos y transmisiones</h2><p>{{ $eventos->total() }} contenidos disponibles</p></div><nav class="uebtv-catalog-filters"><a class="{{ $filter === 'todos' ? 'is-active' : '' }}" href="{{ route('eventos.index') }}">Todos</a><a class="{{ $filter === 'en_vivo' ? 'is-active' : '' }}" href="{{ route('eventos.index', ['filtro' => 'en_vivo']) }}">En vivo</a><a class="{{ $filter === 'anteriores' ? 'is-active' : '' }}" href="{{ route('eventos.index', ['filtro' => 'anteriores']) }}">Anteriores</a></nav></div>
            <section class="uebtv-catalog-grid">
                @forelse($eventos as $evento)
                    <a class="uebtv-catalog-card" href="{{ route('eventos.show', $evento) }}">
                        <div class="uebtv-catalog-card__visual @if($evento->miniatura) has-image @endif" @if($evento->miniatura) style="background-image: url('{{ asset('storage/' . $evento->miniatura) }}')" @endif>
                            <span class="uebtv-catalog-card__play">▶</span>
                            <span class="uebtv-catalog-card__status {{ $evento->estado === 'en_vivo' ? 'is-live' : '' }}"><i></i>{{ $evento->estado === 'en_vivo' ? 'En vivo' : ($evento->video_url ? 'Video disponible' : ucfirst(str_replace('_', ' ', $evento->estado))) }}</span>
                        </div>
                        <div class="uebtv-catalog-card__body"><span>{{ $evento->categoria->nombre ?? 'Institucional' }}</span><h3>{{ $evento->titulo }}</h3><p>{{ $evento->facultad->nombre ?? 'Universidad Estatal de Bolívar' }}</p><small>{{ $evento->fecha_inicio?->format('d M Y · H:i') }}</small></div>
                    </a>
                @empty
                    <div class="uebtv-library-empty"><span>＋</span><div><h3>Aún no hay eventos publicados</h3><p>La programación de UEBTV aparecerá aquí.</p></div></div>
                @endforelse
            </section>
            @if($eventos->hasPages())<div class="uebtv-catalog-pagination">{{ $eventos->links() }}</div>@endif
        </div>
    </main>
</x-app-layout>
