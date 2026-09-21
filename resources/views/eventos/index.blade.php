<x-app-layout>
    <main class="uebtv-catalog-page">
        <div class="uebtv-catalog-container">
            <section class="uebtv-catalog-hero">
                <div><p class="uebtv-kicker uebtv-kicker--navy"><span></span> Programación institucional</p><h1>Todo lo que pasa<br><em>en la UEB.</em></h1><p>Transmisiones, conferencias y contenidos para seguir conectados con nuestra comunidad.</p></div>
                <div class="uebtv-catalog-mark" aria-hidden="true"><strong>UEB</strong><span>TV</span><i></i></div>
            </section>
            <form class="uebtv-catalog-search" action="{{ route('eventos.index') }}" method="GET" role="search">
                <input type="hidden" name="filtro" value="{{ $filter }}">
                <div><label for="catalog-q">Buscar por título</label><input id="catalog-q" name="q" type="search" maxlength="120" value="{{ $filters['q'] ?? '' }}" placeholder="¿Qué quieres ver?"></div>
                <div><label for="catalog-faculty">Facultad</label><select id="catalog-faculty" name="facultad_id"><option value="">Todas las facultades</option>@foreach ($facultades as $facultad)<option value="{{ $facultad->id }}" @selected(($filters['facultad_id'] ?? '') == $facultad->id)>{{ $facultad->nombre }}</option>@endforeach</select></div>
                <div><label for="catalog-category">Categoría</label><select id="catalog-category" name="categoria_id"><option value="">Todas las categorías</option>@foreach ($categorias as $categoria)<option value="{{ $categoria->id }}" @selected(($filters['categoria_id'] ?? '') == $categoria->id)>{{ $categoria->nombre }}</option>@endforeach</select></div>
                <button type="submit">Buscar</button><a href="{{ route('eventos.index') }}">Limpiar filtros</a>
            </form>
            @if ($errors->any())<div class="uebtv-form-errors" role="alert">@foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
            <div class="uebtv-catalog-toolbar"><div><h2>Eventos y transmisiones</h2><p>{{ $eventos->total() }} resultados</p></div>
                <nav class="uebtv-catalog-filters" aria-label="Estado de los eventos">
                    @foreach (['todos' => 'Todos', 'en_vivo' => 'En vivo', 'anteriores' => 'Anteriores', 'proximos' => 'Próximos'] as $value => $label)
                        <a class="{{ $filter === $value ? 'is-active' : '' }}" @if ($filter === $value) aria-current="page" @endif href="{{ route('eventos.index', array_merge($filters, ['filtro' => $value])) }}">{{ $label }}</a>
                    @endforeach
                </nav>
            </div>
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
                    <div class="uebtv-library-empty"><span>＋</span><div><h3>No encontramos eventos</h3><p>Prueba otro título o cambia los filtros para explorar la programación.</p></div></div>
                @endforelse
            </section>
            @if($eventos->hasPages())<div class="uebtv-catalog-pagination">{{ $eventos->links() }}</div>@endif
            @if ($filter !== 'proximos')
                <section class="uebtv-upcoming" aria-labelledby="upcoming-title">
                    <div class="uebtv-catalog-toolbar"><div><h2 id="upcoming-title">Próximos eventos</h2><p>Lo que viene para nuestra comunidad, según tu búsqueda.</p></div><a href="{{ route('eventos.index', array_merge($filters, ['filtro' => 'proximos'])) }}">Ver todos los próximos →</a></div>
                    <div class="uebtv-upcoming-grid">
                        @forelse ($proximos as $proximo)
                            <a class="uebtv-upcoming-card" href="{{ route('eventos.show', $proximo) }}"><time datetime="{{ $proximo->fecha_inicio->toIso8601String() }}">{{ $proximo->fecha_inicio->format('d/m/Y · H:i') }}</time><h3>{{ $proximo->titulo }}</h3><p>{{ $proximo->facultad?->nombre ?? 'Toda la institución' }}</p><span>{{ $proximo->categoria?->nombre }}</span></a>
                        @empty
                            <p>No hay próximos eventos que coincidan con estos filtros.</p>
                        @endforelse
                    </div>
                </section>
            @endif
        </div>
    </main>
</x-app-layout>
