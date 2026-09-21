<x-app-layout>
    @php
        $streamKey = $evento->transmision->stream_key ?? null;
        $streamUrl = $streamKey ? 'http://' . request()->getHost() . '/hls/' . $streamKey . '.m3u8' : '';
        $videoUrl = $evento->transmision?->recordingPath() && $evento->estado === 'finalizado'
            ? route('eventos.recording', $evento) : $evento->video_url;
        $isLive = $evento->estado === 'en_vivo';
    @endphp

    <main class="uebtv-watch-page">
        <div class="uebtv-watch-container">
            <div class="uebtv-watch-breadcrumb"><a href="{{ route('eventos.index') }}">UEBTV</a><span>/</span><span>{{ $evento->categoria->nombre ?? 'Institucional' }}</span></div>
            <div class="uebtv-watch-layout">
                <aside class="uebtv-watch-aside uebtv-watch-aside--left" data-watch-side-panel>
                    <div class="uebtv-watch-aside__header"><p class="uebtv-kicker uebtv-kicker--navy"><span></span> En esta sala</p><span class="uebtv-watch-aside__signal"><i></i> UEBTV</span></div>
                    <div class="uebtv-watch-aside__cover"><span>UEB</span><small>TV</small></div><h2>La señal de nuestra comunidad</h2><p>Disfruta contenidos académicos, institucionales y culturales en un solo lugar.</p><div class="uebtv-watch-aside__rule"></div><a class="uebtv-watch-aside__back" href="{{ route('eventos.index') }}">Explorar toda la programación <span>↗</span></a>
                </aside>
                <section class="uebtv-watch-main">
                    <div class="uebtv-watch-player" data-watch-player>
                        <div class="uebtv-watch-player__topline"><span class="uebtv-watch-live-dot"></span><span>{{ $isLive ? 'Transmisión en directo' : 'Sala UEBTV' }}</span><span class="uebtv-watch-quality">HD</span></div>
                        @if($isLive && $evento->transmision && $streamKey)
                            <div id="liveBadge" class="uebtv-watch-badge"><span class="uebtv-watch-live-dot"></span> EN VIVO <span class="uebtv-watch-viewers"><span aria-hidden="true">◉</span> <span id="viewerCount">{{ $evento->transmision->espectadores_activos ?: 1 }}</span></span></div>
                            <video id="uebVideoPlayer" class="uebtv-video" controls autoplay muted playsinline></video>
                            <script src="https://cdn.jsdelivr.net/npm/hls.js@latest"></script>
                            <script>
                                document.addEventListener('DOMContentLoaded', function () {
                                    const video = document.getElementById('uebVideoPlayer');
                                    const badge = document.getElementById('liveBadge');
                                    const videoSrc = @json($streamUrl);
                                    video.addEventListener('playing', () => badge.classList.add('is-visible'));
                                    video.addEventListener('ended', () => badge.classList.remove('is-visible'));
                                    if (Hls.isSupported()) {
                                        const hls = new Hls({
                                            lowLatencyMode: true,
                                            liveSyncDurationCount: 2,
                                            liveMaxLatencyDurationCount: 4,
                                            maxLiveSyncPlaybackRate: 1.15,
                                            maxBufferLength: 5,
                                            maxMaxBufferLength: 8,
                                            backBufferLength: 0,
                                            enableWorker: true,
                                        });
                                        hls.loadSource(videoSrc);
                                        hls.attachMedia(video);
                                        hls.on(Hls.Events.MANIFEST_PARSED, () => video.play().catch(() => {}));
                                        let lastLiveCorrection = 0;
                                        hls.on(Hls.Events.FRAG_BUFFERED, () => {
                                            const now = Date.now();
                                            const latency = video.duration - video.currentTime;
                                            if (video.readyState >= 3 && latency > 8 && now - lastLiveCorrection > 10000) {
                                                lastLiveCorrection = now;
                                                video.currentTime = Math.max(0, video.duration - 3);
                                            }
                                        });
                                        hls.on(Hls.Events.ERROR, (event, data) => { if (data.fatal) { badge.classList.remove('is-visible'); } });
                                    } else if (video.canPlayType('application/vnd.apple.mpegurl')) {
                                        video.src = videoSrc;
                                        video.addEventListener('loadedmetadata', () => video.play().catch(() => {}));
                                    }
                                });
                            </script>
                        @elseif($videoUrl)
                            <video class="uebtv-video" controls playsinline preload="metadata" @if($evento->miniatura) poster="{{ asset('storage/' . $evento->miniatura) }}" @endif><source src="{{ $videoUrl }}" type="video/mp4">Tu navegador no puede reproducir este video.</video>
                        @else
                            <div class="uebtv-player-empty"><span class="uebtv-player-empty__icon">▶</span><h2>{{ $evento->estado === 'finalizado' ? 'Transmisión finalizada' : 'Video próximamente' }}</h2><p>{{ in_array($evento->transmision?->recording_status, ['waiting', 'processing', 'recording']) && $evento->estado === 'finalizado' ? 'Estamos preparando la grabación. Estará disponible aquí automáticamente.' : ($evento->estado === 'finalizado' ? 'La grabación todavía no está disponible.' : 'Esta sala se habilitará cuando comience la transmisión.') }}</p></div>
                        @endif
                    </div>
                    <div class="uebtv-watch-title-block">
                        <div class="uebtv-watch-title-row"><div><span class="uebtv-watch-category">{{ $evento->categoria->nombre ?? 'Institucional' }}</span><h1>{{ $evento->titulo }}</h1></div><span class="uebtv-watch-state {{ $isLive ? 'is-live' : '' }}"><i></i>{{ $isLive ? 'En vivo' : ($videoUrl ? 'Disponible' : str_replace('_', ' ', ucfirst($evento->estado))) }}</span></div>
                        <div class="uebtv-watch-meta"><span>{{ $evento->facultad->nombre ?? 'Universidad Estatal de Bolívar' }}</span><span>·</span><span>Organiza {{ $evento->organizador->name ?? 'UEB' }}</span><span>·</span><span>{{ $evento->fecha_inicio?->format('d M Y') }}</span></div>
                        <p class="uebtv-watch-description">{{ $evento->descripcion ?: 'Una transmisión oficial de la Universidad Estatal de Bolívar para toda nuestra comunidad.' }}</p>
                        <div class="uebtv-watch-actions"><button type="button" id="shareEvent"><span aria-hidden="true">↗</span> Compartir</button><button type="button" id="copyEventLink"><span aria-hidden="true">▣</span> Copiar enlace</button><a href="{{ route('eventos.index') }}"><span aria-hidden="true">←</span> Catálogo</a><span id="copyFeedback" class="uebtv-copy-feedback" role="status"></span></div>
                    </div>
                </section>
                <aside class="uebtv-live-chat" data-watch-side-panel>
                    <div class="uebtv-live-chat__header"><div><p class="uebtv-kicker uebtv-kicker--navy"><span></span> Comunidad</p><h2>Chat en vivo</h2></div><span class="uebtv-live-chat__online"><i></i> En línea</span></div>
                    <div class="uebtv-live-chat__messages" id="liveChatMessages" data-chat-url="{{ route('eventos.chat.index', $evento) }}" data-can-moderate="{{ $canModerate ? 'true' : 'false' }}" aria-label="Mensajes del chat">
                        @forelse ($chatMessages as $chatMessage)
                            <div class="uebtv-chat-message" data-message-id="{{ $chatMessage['id'] }}"><span class="uebtv-chat-avatar">{{ mb_strtoupper(mb_substr($chatMessage['user'], 0, 1)) }}</span><p><strong>{{ $chatMessage['user'] }}</strong>{{ $chatMessage['message'] }}</p>@if ($canModerate)<button type="button" class="uebtv-chat-remove" data-remove-message="{{ $chatMessage['id'] }}" aria-label="Retirar mensaje de {{ $chatMessage['user'] }}">Retirar</button>@endif</div>
                        @empty
                            <div class="uebtv-chat-empty">Sé el primero en comentar durante la transmisión.</div>
                        @endforelse
                    </div>
                    <p id="chatConnection" class="uebtv-chat-feedback" role="status"></p>
                    <p id="chatError" class="uebtv-chat-feedback is-error" role="alert" hidden></p>
                    @if ($canModerate)<p class="uebtv-live-chat__note">Moderación: puedes retirar mensajes inapropiados.</p>@endif
                    @auth
                        <form class="uebtv-live-chat__form" id="liveChatForm"><input id="liveChatInput" aria-label="Tu mensaje" aria-describedby="chatError" required type="text" maxlength="180" placeholder="Escribe un mensaje..." autocomplete="off"><button type="submit" aria-label="Enviar mensaje">↗</button></form>
                        <p class="uebtv-live-chat__note">Participas como {{ Auth::user()->name }} · Respeta a la comunidad.</p>
                    @else
                        <div class="uebtv-live-chat__login"><p>Inicia sesión para participar en el chat.</p><a href="{{ route('login') }}">Iniciar sesión <span>↗</span></a></div>
                    @endauth
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
            const player = document.querySelector('[data-watch-player]');
            const sidePanels = document.querySelectorAll('[data-watch-side-panel]');
            const syncPanelHeight = () => {
                const isMobile = window.matchMedia('(max-width: 900px)').matches;
                sidePanels.forEach((panel) => panel.style.height = isMobile ? '' : `${player.offsetHeight}px`);
            };
            if (player && sidePanels.length) {
                new ResizeObserver(syncPanelHeight).observe(player);
                syncPanelHeight();
                window.addEventListener('resize', syncPanelHeight);
            }
        });
    </script>
</x-app-layout>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const initial = @json(['state' => $evento->estado, 'ready' => (bool) $evento->transmision?->recordingPath()]);
        let checking = false;
        setInterval(async () => {
            if (document.hidden || checking) return;
            checking = true;
            const controller = new AbortController();
            const timeout = setTimeout(() => controller.abort(), 8000);
            try {
                const response = await fetch(@json(route('eventos.playback-state', $evento)), {
                    headers: { Accept: 'application/json' }, signal: controller.signal,
                });
                if (!response.ok) return;
                const current = await response.json();
                if (current.state !== initial.state || current.ready !== initial.ready) {
                    // Preserve an unsent chat message until the viewer submits it.
                    if (!document.getElementById('liveChatInput')?.value.trim()) window.location.reload();
                }
            } catch {} finally { clearTimeout(timeout); checking = false; }
        }, 15000);
    });
</script>
