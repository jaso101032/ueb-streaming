import React, { useEffect, useRef, useState } from "react";
import { useShell, statusLabel } from "./shared";
import Player from "./Player";
import Chat from "./Chat";
import { VideoAnalyticsInline } from "./VideoAnalytics";

function browserViewerKey() {
    const storageKey = "uebtv-viewer-id";
    let viewer;
    try { viewer = localStorage.getItem(storageKey); } catch {}
    if (!/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i.test(viewer || "")) {
        const bytes = new Uint8Array(16);
        if (globalThis.crypto?.getRandomValues) globalThis.crypto.getRandomValues(bytes);
        else for (let i = 0; i < bytes.length; i++) bytes[i] = Math.floor(Math.random() * 256);
        bytes[6] = (bytes[6] & 0x0f) | 0x40;
        bytes[8] = (bytes[8] & 0x3f) | 0x80;
        const hex = Array.from(bytes, (byte) => byte.toString(16).padStart(2, "0")).join("");
        viewer = `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`;
        try { localStorage.setItem(storageKey, viewer); } catch {}
    }
    return viewer;
}

export default function Watch({
    event: e,
    initialReady,
    videoViews = 0,
    messages,
    canModerate,
    related,
}) {
    const shell = useShell(),
        player = useRef(),
        recordingVideo = useRef(),
        left = useRef(),
        chat = useRef(),
        draft = useRef(""),
        viewProgress = useRef({ last: null, seconds: 0, sent: false, working: false, retryAt: 0 }),
        [feedback, setFeedback] = useState(""),
        [scheduledState, setScheduledState] = useState(e.scheduled?.state),
        [viewerCount, setViewerCount] = useState(e.viewers ?? 0),
        [viewCount, setViewCount] = useState(videoViews),
        [playbackSeconds, setPlaybackSeconds] = useState(0),
        [detailsOpen, setDetailsOpen] = useState(false);
    useEffect(() => {
        const resize = () => {
            [
                [left, 1250],
                [chat, 960],
            ].forEach(([r, b]) => {
                if (r.current)
                    r.current.style.height =
                        window.innerWidth > b
                            ? `${player.current.offsetHeight}px`
                            : "";
            });
        };
        const observer = new ResizeObserver(resize);
        observer.observe(player.current);
        window.addEventListener("resize", resize);
        resize();
        return () => {
            observer.disconnect();
            window.removeEventListener("resize", resize);
        };
    }, []);
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
    useEffect(() => {
        if (e.status !== "en_vivo" || !e.viewersUrl) return;
        const viewer = browserViewerKey();
        let stopped = false, timer, controller;
        async function heartbeat() {
            controller?.abort();
            controller = new AbortController();
            try {
                const response = await fetch(e.viewersUrl, {
                    method: "POST",
                    signal: controller.signal,
                    credentials: "same-origin",
                    headers: { Accept: "application/json", "Content-Type": "application/json", "X-CSRF-TOKEN": shell.csrf },
                    body: JSON.stringify({ viewer }),
                });
                const data = response.ok ? await response.json() : null;
                if (!stopped && Number.isInteger(data?.viewers)) setViewerCount(data.viewers);
            } catch {}
            if (!stopped) timer = setTimeout(heartbeat, 20000);
        }
        heartbeat();
        const resume = () => { if (!document.hidden) { clearTimeout(timer); heartbeat(); } };
        document.addEventListener("visibilitychange", resume);
        return () => { stopped = true; clearTimeout(timer); controller?.abort(); document.removeEventListener("visibilitychange", resume); };
    }, [e.status, e.viewersUrl, shell.csrf]);
    function onVideoProgress(event) {
        const video = event.currentTarget;
        const progress = viewProgress.current;
        const current = video.currentTime;
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
    async function share(native = false) {
        try {
            if (native && navigator.share)
                await navigator.share({
                    title: e.title,
                    url: window.location.href,
                });
            else {
                await navigator.clipboard.writeText(window.location.href);
                setFeedback("Enlace copiado");
            }
        } catch (err) {
            if (err.name !== "AbortError")
                setFeedback("Copia el enlace desde la barra de direcciones.");
        }
    }
    return (
        <div className="uebtv-watch-page watch-experience">
            <div className="uebtv-watch-container">
                <div className="uebtv-watch-breadcrumb">
                    <a href={shell.catalog}>UEBTV</a>
                    <span>/</span>
                    <span>{e.category}</span>
                </div>
                <div className="uebtv-watch-layout">
                    <aside
                        ref={left}
                        className="uebtv-watch-aside uebtv-watch-aside--left"
                    >
                        <div className="uebtv-watch-aside__header">
                            <p className="uebtv-kicker uebtv-kicker--navy">
                                <span />
                                EN ESTA SALA
                            </p>
                            <span className="uebtv-watch-aside__signal">
                                <i className={e.status === "en_vivo" ? "is-live" : ""} />
                                UEBTV
                            </span>
                        </div>
                        <div className="uebtv-watch-aside__cover">
                            <img
                                className="watch-ueb-logo"
                                src={shell.logo}
                                alt="Universidad Estatal de Bolívar"
                                width="200"
                                height="42"
                            />
                            <p>Tu universidad. Tu señal.</p>
                            <span className="watch-brand-caption">TELEVISIÓN UNIVERSITARIA</span>
                        </div>
                        <h2>La señal de nuestra comunidad</h2>
                        <p>
                            Conocimiento, cultura y momentos que nos conectan.
                        </p>
                        <div className="watch-community-signature">
                            Hecho para nuestra comunidad
                            <small>Universidad Estatal de Bolívar</small>
                        </div>
                        <div className="uebtv-watch-aside__rule" />
                        <a
                            className="uebtv-watch-aside__back"
                            href={shell.catalog}
                        >
                            Ver programación <span>↗</span>
                        </a>
                    </aside>
                    <section className="uebtv-watch-main">
                        <div className="uebtv-watch-broadcast-card">
                            <div className="uebtv-watch-player" ref={player}>
                                <Player
                                    event={e}
                                    onScheduledState={setScheduledState}
                                    recordingRef={recordingVideo}
                                    onVideoProgress={onVideoProgress}
                                />
                            </div>
                            <div className="uebtv-watch-player-info" aria-label="Información de la transmisión">
                                {showLiveViewers && <span className="uebtv-watch-player-info__viewers" title={`${viewerCount} ${viewerCount === 1 ? "espectador conectado" : "espectadores conectados"}`} aria-label={`${viewerCount} ${viewerCount === 1 ? "espectador conectado" : "espectadores conectados"}`}>
                                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M16 19v-1.3c0-2-1.8-3.7-4-3.7s-4 1.7-4 3.7V19m4-5a3 3 0 1 0 0-6 3 3 0 0 0 0 6Zm5-5.2a2.5 2.5 0 0 1 0 4.7m1 5.5v-.7c0-1.5-1-2.8-2.5-3.4M7 8.8a2.5 2.5 0 0 0 0 4.7m-1 1.4a3.8 3.8 0 0 0-2 3.4v.7" /></svg>
                                    <strong>{viewerCount}</strong><span className="uebtv-watch-player-info__viewers-label">{viewerCount === 1 ? "espectador conectado" : "espectadores conectados"}</span>
                                </span>}
                                {showVideoViews && <span className="uebtv-watch-player-info__viewers is-video-views" title={`${viewCount} ${viewCount === 1 ? "visualización" : "visualizaciones"}`} aria-label={`${viewCount} ${viewCount === 1 ? "visualización" : "visualizaciones"}`}>
                                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2.5 12s3.5-5 9.5-5 9.5 5 9.5 5-3.5 5-9.5 5-9.5-5-9.5-5Z" /><circle cx="12" cy="12" r="2.5" /></svg>
                                    <strong>{viewCount}</strong><span className="uebtv-watch-player-info__viewers-label">{viewCount === 1 ? "visualización" : "visualizaciones"}</span>
                                </span>}
                                <span className="uebtv-watch-player-info__brand">UEBTV</span>
                            </div>
                            <div className="uebtv-watch-title-block">
                            <div className="uebtv-watch-title-row">
                                <div>
                                    <span className="uebtv-watch-category">
                                        {e.category}
                                    </span>
                                    <h1>{e.title}</h1>
                                </div>
                                <span className={`uebtv-watch-state ${e.status === "en_vivo" ? "is-live" : ""}`}>
                                    <i aria-hidden="true" />
                                    {statusLabel(
                                        e.scheduled
                                            ? {
                                                  ...e,
                                                  scheduled: {
                                                      ...e.scheduled,
                                                      state: scheduledState,
                                                  },
                                              }
                                            : e,
                                    )}
                                </span>
                            </div>
                            <button
                                type="button"
                                className={`uebtv-watch-details-toggle ${detailsOpen ? "is-open" : ""}`}
                                onClick={() => setDetailsOpen((open) => !open)}
                                aria-expanded={detailsOpen}
                            >
                                <span className="uebtv-watch-details-toggle__label">Detalles</span>
                                <span className="uebtv-watch-details-toggle__icons" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="8" /><path d="M12 10.5v5m0-8v.1" /></svg>
                                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M14 6h4v4m0-4-7 7M8 10H6a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2v-2" /></svg>
                                    <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="5" width="16" height="14" rx="2" /><path d="m10 9 5 3-5 3z" /></svg>
                                </span>
                                <svg className="uebtv-watch-details-toggle__chevron" viewBox="0 0 24 24" aria-hidden="true"><path d="m7 10 5 5 5-5" /></svg>
                            </button>
                            <div className={`uebtv-watch-details ${detailsOpen ? "is-open" : ""}`}>
                                <div className="uebtv-watch-meta">
                                    <span>{e.faculty}</span>
                                    <span>·</span>
                                    <span>Organiza {e.organizer}</span>
                                    <span>·</span>
                                    <span>{e.date}</span>
                                </div>
                                {e.license && <p className="uebtv-watch-license">Licencia: {e.license}</p>}
                                <p className="uebtv-watch-description">
                                    {e.description ||
                                        "Una transmisión de la Universidad Estatal de Bolívar para toda nuestra comunidad."}
                                </p>
                                <div className="uebtv-watch-actions">
                                    <button onClick={() => share(true)}>
                                        <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="18" cy="5" r="2" /><circle cx="6" cy="12" r="2" /><circle cx="18" cy="19" r="2" /><path d="m8 11 8-5m-8 7 8 5" /></svg>Compartir
                                    </button>
                                    <button onClick={() => share()}>
                                        <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="9" y="9" width="10" height="10" rx="2" /><path d="M15 9V7a2 2 0 0 0-2-2H7a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h2" /></svg>Copiar enlace
                                    </button>
                                    <a href={shell.channel}><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h3m3 0h3m3 0h2" /><path d="M8 8a5.5 5.5 0 0 1 0 8m8-8a5.5 5.5 0 0 0 0 8" /></svg>Señal continua</a>
                                    <a className="uebtv-watch-catalog-cta" href={shell.catalog}><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="5" width="16" height="14" rx="2" /><path d="m10 9 5 3-5 3z" /></svg>Explorar catálogo</a>
                                    <span role="status">{feedback}</span>
                                </div>
                            </div>
                            </div>
                        </div>
                        {e.analyticsUrl && <VideoAnalyticsInline url={e.analyticsUrl} title={e.title} />}
                    </section>
                    <Chat
                        endpoint={e.chatUrl}
                        initial={messages}
                        canModerate={canModerate}
                        chatEnabled={e.chatEnabled}
                        chatRestricted={e.chatRestricted}
                        chatToggleUrl={e.chatToggleUrl}
                        seekable={e.status === "finalizado" && Boolean(e.videoUrl)}
                        playbackSeconds={playbackSeconds}
                        onSeek={(seconds) => {
                            const video = recordingVideo.current;
                            if (!video) return;
                            video.currentTime = seconds;
                            setPlaybackSeconds(Math.max(0, Math.floor(seconds)));
                            video.play().catch(() => {});
                        }}
                        isRecording={
                            e.status === "finalizado" ||
                            scheduledState === "ended"
                        }
                        canComment={
                            e.status !== "finalizado" &&
                            scheduledState !== "ended"
                        }
                        panelRef={chat}
                        onDraft={(v) => (draft.current = v)}
                    />
                </div>
                <section className="uebtv-library">
                    <div className="uebtv-library__heading">
                        <div>
                            <p className="studio-eyebrow">BIBLIOTECA UEBTV</p>
                            <h2>Más para ver</h2>
                        </div>
                        <a href={`${shell.catalog}?filtro=anteriores`}>
                            Ver anteriores ↗
                        </a>
                    </div>
                    <div className="uebtv-video-grid">
                        {related.map((r) => (
                            <a
                                className="uebtv-video-card"
                                href={r.url}
                                key={r.id}
                            >
                                <div
                                    className="uebtv-video-card__thumb"
                                    style={
                                        r.image
                                            ? {
                                                  backgroundImage: `url(${JSON.stringify(r.image)})`,
                                              }
                                            : undefined
                                    }
                                >
                                    <span className="uebtv-video-card__play">
                                        ▶
                                    </span>
                                    <span className="uebtv-video-card__tag">
                                        {statusLabel(r)}
                                    </span>
                                </div>
                                <div className="uebtv-video-card__body">
                                    <h3>{r.title}</h3>
                                    <p>{r.faculty}</p>
                                </div>
                            </a>
                        ))}
                        {!related.length && (
                            <div className="uebtv-library-empty">
                                <div>
                                    <h3>Nuevas historias están por llegar</h3>
                                    <p>
                                        Pronto encontrarás más contenidos de
                                        nuestra comunidad.
                                    </p>
                                </div>
                            </div>
                        )}
                    </div>
                </section>
            </div>
        </div>
    );
}
