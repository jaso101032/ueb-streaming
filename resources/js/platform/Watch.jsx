import React, { useEffect, useRef, useState } from "react";
import { useShell, statusLabel, ShareButton, AppCard, GuideThumb, browserViewerKey, useViewerPresence, ViewerStat, LiveTimeStat, useNow } from "./shared";
import Player from "./Player";
import Chat from "./Chat";
import { VideoAnalyticsInline } from "./VideoAnalytics";

export default function Watch({
    event: e,
    initialReady,
    videoViews = 0,
    messages,
    canModerate,
    related,
    playlist = null,
    guide = [],
}) {
    const shell = useShell(),
        player = useRef(),
        recordingVideo = useRef(),
        chat = useRef(),
        draft = useRef(""),
        viewProgress = useRef({ last: null, seconds: 0, sent: false, working: false, retryAt: 0 }),
        [scheduledState, setScheduledState] = useState(e.scheduled?.state),
                [viewCount, setViewCount] = useState(videoViews),
        [playbackSeconds, setPlaybackSeconds] = useState(0),
        [detailsOpen, setDetailsOpen] = useState(false),
        // Modo cine (escritorio): video a todo el ancho y el panel debajo.
        [theater, setTheater] = useState(() => { try { return localStorage.getItem("uebtv-theater") === "1"; } catch { return false; } }),
        [videoDuration, setVideoDuration] = useState(0),
        // Pestaña inicial: la conversación, salvo una grabación sin comentarios.
        [tab, setTab] = useState(() => e.status === "finalizado" && !messages.length && guide.length ? "guide" : "chat"),
        // Panel plegable: al ocultarlo aparece la tarjeta de la app (zorrito con QR).
        [collapsed, setCollapsed] = useState(() => { try { return localStorage.getItem("uebtv-panel-collapsed") === "1"; } catch { return false; } }),
        side = useRef(null), collapseButton = useRef(null), expandButton = useRef(null);
    // Directo: el chat es protagonista (celular a pantalla, cine con chat a la derecha).
    const liveRoom = e.status === "en_vivo" && !e.scheduled;
    const setPanelCollapsed = (value) => {
        setCollapsed(value);
        try { localStorage.setItem("uebtv-panel-collapsed", value ? "1" : "0"); } catch {}
        // El foco pasa al botón que aparece, después de la transición.
        window.setTimeout(() => (value ? expandButton : collapseButton).current?.focus({ preventScroll: true }), 360);
    };
    const toggleTheater = React.useCallback(() => setTheater((on) => {
        try { localStorage.setItem("uebtv-theater", on ? "0" : "1"); } catch {}
        return !on;
    }), []);
    useEffect(() => {
        const resize = () => {
            const el = side.current;
            if (!el) return;
            // Escritorio: el panel mide lo mismo que el video, como en Twitch (en
            // directo también en modo cine, porque el chat sigue a la derecha).
            if (window.innerWidth > 960 && (!theater || liveRoom)) el.style.height = `${player.current.offsetHeight}px`;
            // Celular en directo: el chat ocupa el resto de la pantalla (video +
            // datos + chat visibles a la vez, sin desplazar la página).
            else if (window.innerWidth <= 760 && liveRoom && !collapsed) {
                const top = el.getBoundingClientRect().top + window.scrollY;
                el.style.height = `${Math.max(260, Math.round(window.innerHeight - top - 10))}px`;
            } else el.style.height = "";
        };
        const observer = new ResizeObserver(resize);
        observer.observe(player.current);
        // En el celular, los datos sobre el chat también pueden cambiar de alto.
        const info = player.current?.parentElement?.querySelector(".ch-head");
        if (info) observer.observe(info);
        window.addEventListener("resize", resize);
        resize();
        const late = window.setTimeout(resize, 400);
        return () => {
            observer.disconnect();
            window.clearTimeout(late);
            window.removeEventListener("resize", resize);
        };
    }, [theater, liveRoom, collapsed]);
    useEffect(() => {
        if (e.scheduled) return;
        let stopped = false,
            timer,
            controller;
        async function check() {
            if (!document.hidden) {
                controller = new AbortController();
                const timeout = setTimeout(() => controller.abort(), 8000);
                try {
                    const r = await fetch(e.playbackUrl, {
                        signal: controller.signal,
                        headers: { Accept: "application/json" },
                    });
                    if (r.ok) {
                        const d = await r.json();
                        if (
                            !stopped &&
                            (d.state !== e.status ||
                                d.ready !== initialReady) &&
                            !draft.current.trim()
                        )
                            window.location.reload();
                    }
                } catch {
                } finally {
                    clearTimeout(timeout);
                }
            }
            if (!stopped) timer = setTimeout(check, 15000);
        }
        timer = setTimeout(check, 15000);
        return () => {
            stopped = true;
            clearTimeout(timer);
            controller?.abort();
        };
    }, [e.playbackUrl, e.status, initialReady, e.scheduled]);
    const liveViewers = useViewerPresence(e.viewersUrl, e.status === "en_vivo", shell.csrf);
    const viewerCount = liveViewers ?? e.viewers ?? 0;
    const now = useNow(30000);
    function onVideoProgress(event) {
        const video = event.currentTarget;
        const progress = viewProgress.current;
        const current = video.currentTime;
        if (Number.isFinite(video.duration) && Math.round(video.duration) !== Math.round(videoDuration)) setVideoDuration(video.duration);
        if (e.status === "finalizado" && Number.isFinite(current))
            setPlaybackSeconds(Math.max(0, Math.floor(current)));
        if (video.paused || video.seeking || !Number.isFinite(current)) {
            progress.last = current;
            return;
        }
        if (progress.last !== null) {
            const elapsed = current - progress.last;
            if (elapsed > 0 && elapsed <= 1.5) progress.seconds += elapsed;
        }
        progress.last = current;
        if (progress.seconds < 3 || progress.sent || progress.working || Date.now() < progress.retryAt || !e.videoViewsUrl) return;
        progress.working = true;
        fetch(e.videoViewsUrl, {
            method: "POST",
            credentials: "same-origin",
            headers: { Accept: "application/json", "Content-Type": "application/json" },
            body: JSON.stringify({ viewer: browserViewerKey() }),
        }).then(async (response) => {
            if (!response.ok) throw Error("No se pudo registrar la visualización");
            const data = await response.json();
            if (Number.isInteger(data.views)) setViewCount(data.views);
            progress.sent = true;
        }).catch(() => { progress.retryAt = Date.now() + 10000; })
            .finally(() => { progress.working = false; });
    }
    const showLiveViewers = e.status === "en_vivo" && !e.scheduled;
    const showVideoViews = !showLiveViewers && (Boolean(e.videoUrl) || ["playing", "ended"].includes(scheduledState));
    const status = statusLabel(e.scheduled ? { ...e, scheduled: { ...e.scheduled, state: scheduledState } } : e);
    const isRecording = e.status === "finalizado" || scheduledState === "ended";
    const description = e.description || "Una transmisión de la Universidad Estatal de Bolívar para toda nuestra comunidad.";
    const live = e.status === "en_vivo";
    const chatTitle = isRecording ? "Comentarios" : "Chat en vivo";
    return (
        <div className="uebtv-watch-page watch-experience">
            <div className={`wv ${theater ? "is-theater" : ""} ${liveRoom ? "is-live-room" : ""}`}>
                <div className="uebtv-watch-breadcrumb">
                    <a href={shell.catalog}>UEBTV</a>
                    <span>/</span>
                    <span>{e.category}</span>
                </div>
                <div className="wv-grid">
                    <div className="wv-player" ref={player}>
                        <Player event={e} viewers={showLiveViewers ? viewerCount : null} onScheduledState={setScheduledState} recordingRef={recordingVideo} onVideoProgress={onVideoProgress} theater={theater} onTheater={toggleTheater} />
                    </div>
                    <section className="wv-info uebtv-watch-main" aria-label="Información del video">
                        <div className="ch-head">
                        <div className="ch-head__text">
                        <h1 className="wv-title">{e.title}</h1>
                        <div className="wv-subline" role="group" aria-label="Información de la transmisión">
                            {/* En vivo, "EN VIVO" ya está en el video y en el avatar: aquí, los datos. */}
                            {showLiveViewers ? <>
                                <ViewerStat count={viewerCount} />
                                <LiveTimeStat since={e.liveSince} now={now} />
                            </> : <span className="wv-state"><KindIcon status={e.status} scheduled={Boolean(e.scheduled)} />{status}</span>}
                            {showVideoViews && <span className="wv-count" aria-label={`${viewCount} ${viewCount === 1 ? "visualización" : "visualizaciones"}`} title={`${viewCount} ${viewCount === 1 ? "visualización" : "visualizaciones"}`}>
                                <Icon><path d="M2.5 12s3.5-5 9.5-5 9.5 5 9.5 5-3.5 5-9.5 5-9.5-5-9.5-5Z" /><circle cx="12" cy="12" r="2.5" /></Icon><strong>{viewCount}</strong><span className="wv-count__label">{viewCount === 1 ? "visualización" : "visualizaciones"}</span>
                            </span>}
                            <span className="wv-subline__item" title="Categoría"><Icon d="M4 4h7l9 9-7 7-9-9z M8.5 8.5h.01" />{e.category}</span>
                            {!showLiveViewers && <span className="wv-subline__item" title="Fecha"><Icon><rect x="3.5" y="5" width="17" height="15" rx="2.5" /><path d="M8 3v4m8-4v4M3.5 10h17" /></Icon>{e.date}</span>}
                        </div>
                        </div>
                        {liveRoom && <span className="ch-head__share"><ShareButton title={e.title} className="wv-chip ch-head__share-btn" icon={<Icon><circle cx="18" cy="5" r="2" /><circle cx="6" cy="12" r="2" /><circle cx="18" cy="19" r="2" /><path d="m8 11 8-5m-8 7 8 5" /></Icon>} /></span>}
                        </div>
                        <div className="wv-actions" role="toolbar" aria-label="Acciones del video">
                            <ShareButton title={e.title} icon={<Icon><circle cx="18" cy="5" r="2" /><circle cx="6" cy="12" r="2" /><circle cx="18" cy="19" r="2" /><path d="m8 11 8-5m-8 7 8 5" /></Icon>} />
                        </div>
                        <div className={`wv-about ${detailsOpen ? "is-open" : ""}`}>
                            <p className="wv-about__meta"><Icon><circle cx="12" cy="8" r="3.5" /><path d="M5 20a7 7 0 0 1 14 0" /></Icon>{e.faculty} · Organiza {e.organizer}</p>
                            <p className="wv-about__text">{description}</p>
                            {detailsOpen && e.license && <p className="wv-about__license">Licencia: {e.license}</p>}
                            <button type="button" className="wv-about__toggle" aria-expanded={detailsOpen} onClick={() => setDetailsOpen((open) => !open)}>
                                {detailsOpen ? "Mostrar menos" : "Más información"}
                            </button>
                        </div>
                        {e.analyticsUrl && <VideoAnalyticsInline url={e.analyticsUrl} title={e.title} />}
                    </section>
                    <div ref={side} className={`wv-side ${collapsed ? "is-collapsed" : ""}`}>
                    <aside className={`wv-panel ${tab === "guide" ? "is-guide" : "is-chat"}`} ref={chat} aria-label="Conversación y programación" inert={collapsed}>
                        <div className="wv-tabbar">
                        <div className="wv-tabs" role="tablist" aria-label="Panel del video">
                            <button type="button" role="tab" id="wv-tab-chat" aria-controls="wv-tabpanel-chat" aria-selected={tab === "chat"} className="wv-tab" onClick={() => setTab("chat")}>
                                <Icon d="M5 6h14v9H9l-4 3z" />{chatTitle}{isRecording && messages.length > 0 && <span className="wv-tab__count">{messages.length}</span>}
                            </button>
                            <button type="button" role="tab" id="wv-tab-guide" aria-controls="wv-tabpanel-guide" aria-selected={tab === "guide"} className="wv-tab" onClick={() => setTab("guide")}>
                                <Icon><rect x="3.5" y="5" width="17" height="15" rx="2.5" /><path d="M8 3v4m8-4v4M3.5 10h17" /></Icon>Programación
                            </button>
                        </div>
                        <button ref={collapseButton} type="button" className="wv-side__toggle" onClick={() => setPanelCollapsed(true)} aria-label="Ocultar panel" title="Ocultar panel">
                            <Icon d="m6 15 6-6 6 6" />
                        </button>
                        </div>
                        <div className="wv-tabpanel" role="tabpanel" id="wv-tabpanel-chat" aria-labelledby="wv-tab-chat" hidden={tab !== "chat"}>
                            <Chat
                                endpoint={e.chatUrl}
                                initial={messages}
                                canModerate={canModerate}
                                chatEnabled={e.chatEnabled}
                                chatRestricted={e.chatRestricted}
                                chatToggleUrl={e.chatToggleUrl}
                                seekable={e.status === "finalizado" && Boolean(e.videoUrl)}
                                playbackSeconds={playbackSeconds}
                                videoDuration={videoDuration}
                                onSeek={(seconds) => {
                                    const video = recordingVideo.current;
                                    if (!video) return;
                                    video.currentTime = seconds;
                                    setPlaybackSeconds(Math.max(0, Math.floor(seconds)));
                                    video.play().catch(() => {});
                                }}
                                isRecording={isRecording}
                                canComment={e.status !== "finalizado" && scheduledState !== "ended"}
                                onDraft={(v) => (draft.current = v)}
                            />
                        </div>
                        <div className="wv-tabpanel" role="tabpanel" id="wv-tabpanel-guide" aria-labelledby="wv-tab-guide" hidden={tab !== "guide"}>
                            <ProgramGuide items={guide} currentId={e.id} channel={shell.channel} />
                        </div>
                    </aside>
                    <div className="wv-side__app" inert={!collapsed}>
                        <button ref={expandButton} type="button" className="wv-side__show" onClick={() => setPanelCollapsed(false)}>
                            <span className="wv-side__show-icon" aria-hidden="true"><Icon d="M5 6h14v9H9l-4 3z" /></span>
                            <span className="wv-side__show-text">Mostrar {chatTitle.toLowerCase()} y programación</span>
                            {isRecording && messages.length > 0 && <b>{messages.length}</b>}
                            <span className="wv-side__show-arrow" aria-hidden="true"><Icon d="m6 9 6 6 6-6" /></span>
                        </button>
                        <AppCard titleId="wv-app-title" />
                    </div>
                    </div>
                    <div className="wv-more-group">
                    {/* Lista de la misma carrera o facultad: se agrupa como una playlist. */}
                    {playlist?.items?.length > 0 && <section className="wv-more wv-playlist" aria-labelledby="wv-playlist-title">
                        <div className="wv-more__heading">
                            <h2 id="wv-playlist-title"><span className="wv-playlist__icon" aria-hidden="true"><Icon d="M4 6h11M4 11h11M4 16h7m6-3 4 2.5-4 2.5z" /></span>{playlist.title}<b>{playlist.items.length}</b></h2>
                            <a href={playlist.url}>Ver todos ↗</a>
                        </div>
                        <div className="wv-more__list">{playlist.items.map((r) => <MoreCard key={r.id} item={r} logo={shell.logo} />)}</div>
                    </section>}
                    <section className="wv-more" aria-labelledby="wv-more-title">
                        <div className="wv-more__heading">
                            <h2 id="wv-more-title">Más para ver</h2>
                            <a href={shell.catalog}>Ver todo el catálogo ↗</a>
                        </div>
                        {related.length ? <div className="wv-more__list">
                            {related.map((r) => <MoreCard key={r.id} item={r} logo={shell.logo} />)}
                        </div> : <p className="wv-more__empty">Pronto encontrarás más contenidos de nuestra comunidad.</p>}
                    </section>
                    </div>
                </div>
            </div>
        </div>
    );
}

function MoreCard({ item: r, logo }) {
    return (
        <a className="wv-card" href={r.url}>
            <span className="wv-card__thumb" style={r.image ? { backgroundImage: `url(${JSON.stringify(r.image)})` } : undefined}>
                {!r.image && <img src={logo} alt="" className="wv-card__logo" />}
                <span className={`wv-card__tag ${r.status === "en_vivo" ? "is-live" : ""}`}><KindIcon status={r.status} scheduled={Boolean(r.scheduled)} />{statusLabel(r)}</span>
            </span>
            <span className="wv-card__body">
                <strong>{r.title}</strong>
                <small>{r.category} · {r.date}</small>
            </span>
        </a>
    );
}

function Icon({ d, children }) {
    return <svg className="wv-icon" viewBox="0 0 24 24" aria-hidden="true">{d ? <path d={d} /> : children}</svg>;
}

/** Ícono según el tipo de contenido: directo, programa del canal, próximo o grabación. */
function KindIcon({ status, scheduled }) {
    if (status === "en_vivo") return <i className="wv-live-dot" aria-hidden="true" />;
    if (status === "programado") return scheduled
        ? <Icon><rect x="3" y="6" width="18" height="12" rx="2" /><path d="M8 3l4 3 4-3" /></Icon>
        : <Icon><rect x="3.5" y="5" width="17" height="15" rx="2.5" /><path d="M8 3v4m8-4v4M3.5 10h17" /></Icon>;
    return <Icon d="M8 5.5v13L18.5 12z" />;
}

const guideTime = (iso) => {
    const date = new Date(iso);
    if (Number.isNaN(date.getTime())) return "";
    const time = date.toLocaleTimeString("es-EC", { hour: "2-digit", minute: "2-digit" });
    const today = new Date(), tomorrow = new Date(); tomorrow.setDate(today.getDate() + 1);
    if (date.toDateString() === today.toDateString()) return time;
    if (date.toDateString() === tomorrow.toDateString()) return `Mañana · ${time}`;
    return `${date.toLocaleDateString("es-EC", { weekday: "short", day: "numeric", month: "short" })} · ${time}`;
};

/** Programación: lo que está al aire y lo que viene en UEBTV. */
function ProgramGuide({ items, currentId, channel }) {
    const now = items.filter((item) => item.when === "now"), next = items.filter((item) => item.when === "next");
    const row = (item) => (
        <li key={`${item.kind}-${item.id}`}>
            <a className={`wv-guide__item ${item.id === currentId ? "is-current" : ""}`} href={item.url} aria-current={item.id === currentId ? "page" : undefined}>
                <GuideThumb image={item.image} live={item.kind === "live" && item.when === "now"} />
                <span className="wv-guide__text">
                <span className={`wv-guide__kind is-${item.kind}`}>{item.kind === "live" ? <><i className="wv-live-dot" aria-hidden="true" />Directo</> : <><Icon><rect x="3" y="6" width="18" height="12" rx="2" /><path d="M8 3l4 3 4-3" /></Icon>Canal 24/7</>}</span>
                <strong>{item.title}</strong>
                <small>{item.when === "now" ? (item.id === currentId ? "Estás viendo esto" : "Ahora") : guideTime(item.starts_at)}{item.category ? ` · ${item.category}` : ""}</small>
                </span>
            </a>
        </li>
    );
    return (
        <div className="wv-guide">
            {now.length > 0 && <section aria-labelledby="wv-guide-now"><h3 id="wv-guide-now"><i className="wv-live-dot" aria-hidden="true" />Ahora en UEBTV</h3><ul>{now.map(row)}</ul></section>}
            {next.length > 0 && <section aria-labelledby="wv-guide-next"><h3 id="wv-guide-next">A continuación</h3><ul>{next.map(row)}</ul></section>}
            {!items.length && <div className="wv-guide__empty">
                <Icon><rect x="3.5" y="5" width="17" height="15" rx="2.5" /><path d="M8 3v4m8-4v4M3.5 10h17" /></Icon>
                <p><strong>No hay programación anunciada</strong>Cuando se programen transmisiones o videos, aparecerán aquí.</p>
            </div>}
            <div className="wv-guide__links">
                <a href={channel}><Icon><path d="M5 12h3m3 0h3m3 0h2" /><path d="M8 8a5.5 5.5 0 0 1 0 8m8-8a5.5 5.5 0 0 0 0 8" /></Icon>Ver señal 24/7</a>
            </div>
        </div>
    );
}
