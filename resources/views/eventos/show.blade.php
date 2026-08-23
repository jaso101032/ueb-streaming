<x-app-layout>
    @php
        $streamKey = $evento->transmision->stream_key ?? null;
        $streamUrl = $streamKey ? 'http://' . request()->getHost() . '/hls/' . $streamKey . '.m3u8' : '';
        $videoUrl = $evento->video_url;
        $isLive = $evento->estado === 'en_vivo';
    @endphp

    <main class="uebtv-watch-page">
        <div class="uebtv-watch-container">
            <div class="uebtv-watch-breadcrumb"><a href="{{ route('eventos.index') }}">UEBTV</a><span>/</span><span>{{ $evento->categoria->nombre ?? 'Institucional' }}</span></div>
            <div class="uebtv-watch-layout">
                <section class="uebtv-watch-main">
                    <div class="uebtv-watch-player">
                        <div class="uebtv-watch-player__topline"><span class="uebtv-watch-live-dot"></span><span>{{ $isLive ? 'Transmisión en directo' : 'Sala UEBTV' }}</span><span class="uebtv-watch-quality">HD</span></div>
                        @if($isLive && $evento->transmision && $streamKey)
                            <div id="liveBadge" class="uebtv-watch-badge"><span class="uebtv-watch-live-dot"></span> EN VIVO <span class="uebtv-watch-viewers"><span aria-hidden="true">◉</span> <span id="viewerCount">{{ $evento->transmision->espectadores_activos ?: 1 }}</span></span></div>
                            <video id="uebVideoPlayer" class="uebtv-video" controls autoplay muted playsinline></video>
                            <div id="playerMessage" class="uebtv-player-message" hidden>La señal está preparando su emisión...</div>
                            <script src="https://cdn.jsdelivr.net/npm/hls.js@latest"></script>
                            <script>
                                document.addEventListener('DOMContentLoaded', function () {
                                    const video = document.getElementById('uebVideoPlayer');
                                    const badge = document.getElementById('liveBadge');
                                    const message = document.getElementById('playerMessage');
                                    const videoSrc = @json($streamUrl);
                                    const setMessage = (visible) => { message.hidden = !visible; };
                                    video.addEventListener('playing', () => { badge.classList.add('is-visible'); setMessage(false); });
                                    video.addEventListener('waiting', () => setMessage(true));
                                    video.addEventListener('ended', () => badge.classList.remove('is-visible'));
                                    if (Hls.isSupported()) {
                                        const hls = new Hls({ liveSyncDurationCount: 1.5, maxLiveSyncPlaybackRate: 1.2, enableWorker: true });
                                        hls.loadSource(videoSrc);
                                        hls.attachMedia(video);
                                        hls.on(Hls.Events.MANIFEST_PARSED, () => video.play().catch(() => setMessage(true)));
                                        hls.on(Hls.Events.ERROR, (event, data) => { if (data.fatal) { badge.classList.remove('is-visible'); setMessage(true); } });
                                    } else if (video.canPlayType('application/vnd.apple.mpegurl')) {
                                        video.src = videoSrc;
                                        video.addEventListener('loadedmetadata', () => video.play().catch(() => setMessage(true)));
                                    }
                                });
                            </script>
                        @elseif($videoUrl)
                            <video class="uebtv-video" controls playsinline preload="metadata" @if($evento->miniatura) poster="{{ asset('storage/' . $evento->miniatura) }}" @endif><source src="{{ $videoUrl }}" type="video/mp4">Tu navegador no puede reproducir este video.</video>
                        @else
                            <div class="uebtv-player-empty"><span class="uebtv-player-empty__icon">▶</span><h2>Video próximamente</h2><p>Esta sala se habilitará cuando comience la transmisión.</p></div>
                        @endif
                    </div>
                    <div class="uebtv-watch-title-block">
                        <div class="uebtv-watch-title-row"><div><span class="uebtv-watch-category">{{ $evento->categoria->nombre ?? 'Institucional' }}</span><h1>{{ $evento->titulo }}</h1></div><span class="uebtv-watch-state {{ $isLive ? 'is-live' : '' }}"><i></i>{{ $isLive ? 'En vivo' : ($videoUrl ? 'Disponible' : str_replace('_', ' ', ucfirst($evento->estado))) }}</span></div>
                        <div class="uebtv-watch-meta"><span>{{ $evento->facultad->nombre ?? 'Universidad Estatal de Bolívar' }}</span><span>·</span><span>Organiza {{ $evento->organizador->name ?? 'UEB' }}</span><span>·</span><span>{{ $evento->fecha_inicio?->format('d M Y') }}</span></div>
                        <p class="uebtv-watch-description">{{ $evento->descripcion ?: 'Una transmisión oficial de la Universidad Estatal de Bolívar para toda nuestra comunidad.' }}</p>
                        <div class="uebtv-watch-actions"><button type="button" id="shareEvent"><span aria-hidden="true">↗</span> Compartir</button><button type="button" id="copyEventLink"><span aria-hidden="true">▣</span> Copiar enlace</button><a href="{{ route('eventos.index') }}"><span aria-hidden="true">←</span> Catálogo</a><span id="copyFeedback" class="uebtv-copy-feedback" role="status"></span></div>
                    </div>
                </section>
                <aside class="uebtv-watch-aside">
                    <div class="uebtv-watch-aside__header"><p class="uebtv-kicker uebtv-kicker--navy"><span></span> En esta sala</p><span class="uebtv-watch-aside__signal"><i /> UEBTV</span></div>
                    <div class="uebtv-watch-aside__cover"><span>UEB</span><small>TV</small></div><h2>La señal de nuestra comunidad</h2><p>Disfruta contenidos académicos, institucionales y culturales en un solo lugar.</p><div class="uebtv-watch-aside__rule"></div><a class="uebtv-watch-aside__back" href="{{ route('eventos.index') }}">Explorar toda la programación <span>↗</span></a>
                </aside>
            </div>
            <section class="uebtv-library"><div class="uebtv-library__heading"><div><p class="uebtv-kicker uebtv-kicker--navy"><span></span> Biblioteca UEBTV</p><h2>Más para ver</h2><p class="uebtv-library__subheading">Transmisiones anteriores y contenidos destacados de la Universidad.</p></div><a href="{{ route('eventos.index', ['filtro' => 'anteriores']) }}">Ver anteriores <span>↗</span></a></div><div class="uebtv-video-grid">
                @forelse($eventosRelacionados as $relacionado)
                    <a class="uebtv-video-card" href="{{ route('eventos.show', $relacionado) }}"><div class="uebtv-video-card__thumb" @if($relacionado->miniatura) style="background-image: url('{{ asset('storage/' . $relacionado->miniatura) }}')" @endif><span class="uebtv-video-card__play">▶</span><span class="uebtv-video-card__tag">{{ $relacionado->estado === 'en_vivo' ? 'EN VIVO' : 'UEBTV' }}</span></div><div class="uebtv-video-card__body"><h3>{{ $relacionado->titulo }}</h3><p>{{ $relacionado->facultad->siglas ?? 'Universidad Estatal de Bolívar' }}</p></div></a>
                @empty
                    <div class="uebtv-library-empty"><span>＋</span><div><h3>Tu biblioteca está por comenzar</h3><p>Los videos grabados de la comunidad UEB aparecerán aquí cuando se registre su enlace en el evento.</p><a href="{{ route('eventos.index', ['filtro' => 'anteriores']) }}">Explorar transmisiones anteriores <span>↗</span></a></div></div>
                @endforelse
            </div></section>
        </div>
    </main>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const feedback = document.getElementById('copyFeedback');
            const copyLink = async () => { await navigator.clipboard.writeText(window.location.href); feedback.textContent = 'Enlace copiado'; setTimeout(() => feedback.textContent = '', 2200); };
            document.getElementById('copyEventLink')?.addEventListener('click', copyLink);
            document.getElementById('shareEvent')?.addEventListener('click', async () => { if (navigator.share) { await navigator.share({ title: @json($evento->titulo), url: window.location.href }); } else { await copyLink(); } });
        });
    </script>
</x-app-layout>
