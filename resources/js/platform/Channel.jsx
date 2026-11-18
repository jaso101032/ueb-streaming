import React, { useCallback, useEffect, useRef, useState } from "react";
import Player, { BrandedVideo, clockedMediaTarget, isTimeBuffered } from "./Player";
import { useShell, statusLabel, ShareButton, AppCard, GuideThumb } from "./shared";
import Chat from "./Chat";
import { createPortal } from "react-dom";

export function durationLabel(seconds = 0) {
    const s = Math.max(0, Math.floor(seconds));
    return `${Math.floor(s / 3600)} h ${Math.floor((s % 3600) / 60)} min ${s % 60} s`;
}

function AgendaDialog({ items, onClose, returnFocusRef }) {
    const dialog = useRef(null);
    const groups = (items || []).reduce((result, item) => {
        const date = new Date(item.starts_at);
        const key = `${date.getFullYear()}-${date.getMonth()}-${date.getDate()}`;
        (result[key] ||= { date, items: [] }).items.push(item);
        return result;
    }, {});

    useEffect(() => {
        const root = dialog.current;
        const opener = document.activeElement;
        const focusable = () => [...(root?.querySelectorAll('a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])') || [])].filter((element) => element.getClientRects().length);
        const closeOnEscape = (event) => {
            if (event.key === "Escape") onClose();
            if (event.key !== "Tab") return;
            const items = focusable();
            if (!items.length) { event.preventDefault(); root?.focus(); return; }
            const first = items[0], last = items[items.length - 1];
            if (event.shiftKey && (document.activeElement === first || !root?.contains(document.activeElement))) {
                event.preventDefault(); last.focus();
            } else if (!event.shiftKey && (document.activeElement === last || !root?.contains(document.activeElement))) {
                event.preventDefault(); first.focus();
            }
        };
        const content = document.getElementById("contenido");
        const wasInert = content?.inert ?? false;
        if (content) content.inert = true;
        (root?.querySelector("button") || root)?.focus();
        document.addEventListener("keydown", closeOnEscape);
        document.body.classList.add("has-channel-agenda-open");
        return () => {
            document.removeEventListener("keydown", closeOnEscape);
            document.body.classList.remove("has-channel-agenda-open");
            if (content) content.inert = wasInert;
            returnFocusRef?.current?.focus?.() || opener?.focus?.();
        };
    }, [onClose, returnFocusRef]);

    const days = Object.values(groups);
    return createPortal(<div className="channel-agenda-modal-backdrop" onMouseDown={(event) => {
        if (event.target === event.currentTarget) onClose();
    }}>
        <section ref={dialog} className="channel-agenda-modal" role="dialog" aria-modal="true" aria-labelledby="channel-agenda-modal-title" tabIndex={-1}>
            <header className="channel-agenda-modal-header">
                <div className="channel-agenda-modal-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.7"><rect x="3" y="5" width="18" height="16" rx="3"/><path d="M7 3v4m10-4v4M3 10h18m-13 4h3m-3 3h7"/></svg>
                </div>
                <div><p className="studio-eyebrow">UEB · TV</p><h2 id="channel-agenda-modal-title">Programación</h2><p>Todos los contenidos que vienen en la señal.</p></div>
                <button type="button" className="channel-agenda-modal-close" onClick={onClose} aria-label="Cerrar programación">×</button>
            </header>
            <div className="channel-agenda-modal-content">
                {days.length ? days.map(({ date, items: dayItems }) => <section className="channel-agenda-day" key={`${date.getFullYear()}-${date.getMonth()}-${date.getDate()}`}>
                    <h3><span>{date.toLocaleDateString("es-EC", { weekday: "short" })}</span>{date.toLocaleDateString("es-EC", { day: "numeric", month: "long", year: "numeric" })}</h3>
                    <div className="channel-agenda-day-items">{dayItems.map((item) => <article className="channel-agenda-modal-item" key={item.id}>
                        <time>{new Date(item.starts_at).toLocaleTimeString("es-EC", { hour: "2-digit", minute: "2-digit" })}</time>
                        <span className="channel-agenda-modal-timeline" aria-hidden="true" />
                        <div><h4>{item.title}</h4><p>{durationLabel(item.duration)} <span>·</span> Pregrabado</p></div>
                    </article>)}</div>
                </section>) : <div className="channel-agenda-modal-empty"><span aria-hidden="true">✦</span><h3>Aún no hay emisiones programadas</h3><p>Cuando se agreguen contenidos, aparecerán aquí organizados por día.</p></div>}
            </div>
            <footer className="channel-agenda-modal-footer"><span>{items?.length || 0} {items?.length === 1 ? "emisión programada" : "emisiones programadas"}</span><button type="button" onClick={onClose}>Listo</button></footer>
        </section>
    </div>, document.body);
}

// The server owns the clock. Refreshes and late arrivals use the same position.
function ChannelVideo({ snapshot, received, startMuted, onMuteChange }) {
    const isAd = snapshot.mode === "advertisement";
    const media = isAd ? snapshot.advertisement : snapshot.event.scheduled;
    const video = useRef(null);
    const latest = useRef({ snapshot, received });
    latest.current = { snapshot, received };
    const [notice, setNotice] = useState("");
    const [retry, setRetry] = useState(0);
    const manualPause = useRef(false);
    const autoplayBlocked = useRef(false);
    const playPending = useRef(false);
    const retryTimer = useRef(null);
    const retryAttempts = useRef(0);
    const lastProgress = useRef({ at: performance.now(), position: 0 });
    const lastSeekAt = useRef(0);
    const previousClockPosition = useRef(snapshot.playback.position);
    const align = (force = false) => {
        const { snapshot: s, received: t } = latest.current;
        const v = video.current;
        if (!v || v.readyState < 1 || v.seeking) return;
        const now = performance.now();
        const target = clockedMediaTarget(v,
            s.playback.position +
                (s.playback.state === "playing"
                    ? (now - t) / 1000
                    : 0),
            s.playback.duration,
        );
        const drift = Math.abs(v.currentTime - target);
        // An initial join needs one seek. Subsequent clock corrections must
        // not abort an in-flight range request or throw away the current buffer.
        if ((force && drift > 1.5) || (!force && drift > 10 && v.readyState >= 3 && now - lastSeekAt.current > 15000 && isTimeBuffered(v, target))) {
            lastSeekAt.current = now;
            v.currentTime = target;
        }
        if (v.playbackRate !== 1) v.playbackRate = 1;
    };
    const play = async (fromUser = false) => {
        const v = video.current;
        if (!v || playPending.current) return;
        if (fromUser) { manualPause.current = false; autoplayBlocked.current = false; }
        if (manualPause.current || autoplayBlocked.current || latest.current.snapshot.playback.state === "paused") return;
        playPending.current = true;
        try {
            await v.play();
            setNotice("");
        } catch (error) {
            // Audible autoplay is forbidden on some phones. Join muted first;
            // the viewer can turn sound back on with the existing control.
            if (error.name === "NotAllowedError" && !fromUser && !v.muted) {
                v.muted = true;
                try { await v.play(); setNotice(""); return; } catch { /* A tap is required. */ }
            }
            if (error.name === "NotAllowedError") {
                autoplayBlocked.current = true;
                setNotice("Pulsa reproducir para unirte a la señal.");
            } else if (error.name !== "AbortError") reconnect();
        } finally {
            playPending.current = false;
        }
    };
    const reconnect = () => {
        if (retryTimer.current || manualPause.current || autoplayBlocked.current) return;
        const delay = Math.min(1000 * 2 ** Math.min(retryAttempts.current++, 4), 16000);
        setNotice("La señal se interrumpió. Reconectando automáticamente…");
        retryTimer.current = window.setTimeout(() => {
            retryTimer.current = null;
            lastProgress.current = { at: performance.now(), position: 0 };
            setRetry((current) => current + 1);
        }, delay);
    };
    useEffect(() => {
        const v = video.current;
        const wrapped = snapshot.playback.position + 5 < previousClockPosition.current;
        previousClockPosition.current = snapshot.playback.position;
        if (wrapped && v?.readyState >= 1) {
            // One advertisement can loop under the same ID. Move back to the
            // new cycle explicitly; React does not remount the video in that case.
            v.currentTime = clockedMediaTarget(v, snapshot.playback.position, snapshot.playback.duration);
            lastSeekAt.current = performance.now();
            lastProgress.current.at = performance.now();
        } else align();
        if (snapshot.playback.state === "paused") video.current?.pause();
        else if (video.current?.readyState >= 1 && video.current.paused && !manualPause.current && !autoplayBlocked.current) play();
    }, [snapshot]);
    useEffect(() => {
        const timer = window.setInterval(() => {
            const v = video.current;
            if (document.hidden || !v || manualPause.current || autoplayBlocked.current || latest.current.snapshot.playback.state !== "playing") return;
            const idle = performance.now() - lastProgress.current.at;
            // BrandedVideo muestra el búfer sin tapar el anuncio ni pedir un clic.
            if (idle > 30000 && (!v.paused || v.readyState < 2)) reconnect();
        }, 2000);
        return () => { window.clearInterval(timer); window.clearTimeout(retryTimer.current); };
    }, []);
    const withRetry = (url) => retry ? `${url}${url.includes("?") ? "&" : "?"}reconnect=${retry}` : url;
    const mediaUrl = withRetry(media.mediaUrl);
    // La publicidad es corta y se queda en MP4; la programación usa calidades adaptativas.
    const adaptiveUrl = !isAd && media.adaptiveUrl ? withRetry(media.adaptiveUrl) : undefined;
    return (
        <>
            <BrandedVideo
                videoRef={video}
                src={mediaUrl}
                adaptiveSrc={adaptiveUrl}
                mediaTitle={isAd ? `Publicidad institucional · ${media.title}` : snapshot.event.title}
                subtitles={(isAd ? media.subtitlesUrl : snapshot.event?.subtitlesUrl) || undefined}
                live={false}
                allowPause
                allowPictureInPicture={!isAd}
                startMuted={startMuted}
                autoStart={false}
                quietChrome
                onMuteChange={onMuteChange}
                onEnded={() => {
                    // El anuncio se repite al instante, sin esperar al reloj del servidor.
                    const v = video.current;
                    if (!isAd || !v || manualPause.current) return;
                    v.currentTime = 0;
                    play();
                }}
                onLoadedMetadata={() => { lastProgress.current.at = performance.now(); align(true); play(); }}
                onSeeked={() => { lastProgress.current.at = performance.now(); }}
                onUserPause={() => { manualPause.current = true; }}
                onUserResume={() => { manualPause.current = false; autoplayBlocked.current = false; align(true); }}
                onPlay={() => { autoplayBlocked.current = false; }}
                onPlaying={() => { lastProgress.current.at = performance.now(); setNotice(""); }}
                onTimeUpdate={() => {
                    const position = video.current?.currentTime ?? 0;
                    if (!video.current?.seeking && Math.abs(position - lastProgress.current.position) > 0.1) {
                        lastProgress.current = { at: performance.now(), position };
                        retryAttempts.current = 0;
                        setNotice("");
                    }
                }}
                onError={reconnect}
            >
                {isAd && <span className="channel-ad-indicator" title="Publicidad institucional" aria-label="Publicidad institucional">P</span>}
                {notice && (
                    <div className="react-player-notice" role="status">
                        <p>{notice}</p>
                        <button className="studio-button" onClick={() => play(true)}>
                            Reproducir
                        </button>
                    </div>
                )}
            </BrandedVideo>
        </>
    );
}

export default function Channel({ stateUrl, recent = [] }) {
    const shell = useShell();
    const tvMode = new URLSearchParams(window.location.search).get("modo") === "tv";
    const [data, setData] = useState(null);
    const [agendaOpen, setAgendaOpen] = useState(false);
    const agendaButton = useRef(null);
    const [received, setReceived] = useState(0);
    const [error, setError] = useState("");
    const [channelMuted, setChannelMuted] = useState(!tvMode);
    // Pestaña del panel: automática (chat si hay emisión) hasta que la persona elige.
    const [chosenTab, setChosenTab] = useState(null);
    // null = automático: al entrar, plegado (botón "Mostrar programación" + la
    // app) salvo que haya conversación al aire. Si la persona elige, se recuerda.
    const [chosenCollapsed, setChosenCollapsed] = useState(() => { try { const v = localStorage.getItem("uebtv-channel-panel-collapsed"); return v === null ? null : v === "1"; } catch { return null; } });
    const collapseButton = useRef(null), expandButton = useRef(null);
    const setPanelCollapsed = (value) => {
        setChosenCollapsed(value);
        try { localStorage.setItem("uebtv-channel-panel-collapsed", value ? "1" : "0"); } catch {}
        window.setTimeout(() => (value ? expandButton : collapseButton).current?.focus({ preventScroll: true }), 360);
    };
    const [clock, setClock] = useState(() => Date.now());
    useEffect(() => { const timer = setInterval(() => setClock(Date.now()), 30000); return () => clearInterval(timer); }, []);
    const rail = useRef(null);
    const screenFrame = useRef(null);
    useEffect(() => {
        // La tarjeta de la app (panel plegado) termina a la altura del video.
        const frame = screenFrame.current, column = rail.current;
        if (!frame || !column || typeof ResizeObserver === "undefined") return undefined;
        const sync = () => column.style.setProperty("--cv-video-h", `${frame.offsetHeight}px`);
        const observer = new ResizeObserver(sync);
        observer.observe(frame); sync();
        return () => observer.disconnect();
    }, [tvMode]);
    useEffect(() => {
        if (!tvMode) return undefined;
        document.documentElement.classList.add("uebtv-tv-mode");
        document.title = "UEBTV · Señal continua";
        return () => document.documentElement.classList.remove("uebtv-tv-mode");
    }, [tvMode]);
    useEffect(() => {
        let stopped = false,
            timer,
            controller;
        async function poll() {
            controller = new AbortController();
            const timeout = setTimeout(() => controller.abort(), 8000);
            try {
                const start = performance.now();
                const response = await fetch(stateUrl, {
                    cache: "no-store",
                    headers: { Accept: "application/json" },
                    signal: controller.signal,
                });
                if (!response.ok) throw new Error();
                const next = await response.json();
                if (stopped) return;
                if (next.playback?.state === "playing")
                    next.playback.position +=
                        (performance.now() - start) / 2000;
                setReceived(performance.now());
                setData(next);
                setError("");
            } catch {
                if (!stopped) setError("Reconectando con la programación…");
            } finally {
                clearTimeout(timeout);
                if (!stopped) timer = setTimeout(poll, 3000);
            }
        }
        poll();
        return () => {
            stopped = true;
            clearTimeout(timer);
            controller?.abort();
        };
    }, [stateUrl]);
    const live = data?.mode === "live";
    const advertising = data?.mode === "advertisement";
    const playing = live || data?.mode === "scheduled" || advertising;
    const next = data?.next?.[0];
    const closeAgenda = useCallback(() => setAgendaOpen(false), []);
    const chatEvent = live || data?.mode === "scheduled" ? data?.event : null;
    // Sin programa ni directo (publicidad o pausa) no hay conversación: solo la
    // programación. El panel se puede plegar y entonces aparece la app (zorrito).
    const tab = chatEvent ? chosenTab || "chat" : "guide";
    const collapsed = chosenCollapsed ?? !chatEvent;
    // Sin chat ni emisiones anunciadas, la programación es corta: el panel toma su
    // alto natural y la tarjeta de la app ocupa el resto de la columna.
    const quietGuide = Boolean(data) && !chatEvent && !data.next?.length;
    const room = roomStatus(data, clock);
    return (
        <div className={`studio-page channel-page cv ${tvMode ? "channel-page--tv" : ""}`}>
            {error && (
                <p role="status" className="studio-alert">
                    {error}
                </p>
            )}
            <div className="channel-layout">
                <div className="cv-left">
                <section className="channel-main" aria-label="UEB - TV">
                    <div ref={screenFrame} className="channel-screen-frame">
                        <div
                            className={`channel-screen ${playing ? "is-playing" : "is-idle"}`}
                        >
                            {live ? (
                                <Player
                                    key={`live-${data.event.id}`}
                                    event={data.event}
                                    startMuted={channelMuted}
                                    onMuteChange={setChannelMuted}
                                />
                            ) : data?.mode === "scheduled" || advertising ? (
                                <ChannelVideo
                                    key={advertising ? `ad-${data.advertisement.id}` : `video-${data.event.id}`}
                                    snapshot={data}
                                    received={received}
                                    startMuted={channelMuted}
                                    onMuteChange={setChannelMuted}
                                />
                            ) : (
                                <div className="channel-idle">
                                    <div
                                        className="channel-orbits"
                                        aria-hidden="true"
                                    >
                                        <i />
                                        <i />
                                        <i />
                                    </div>
                                    <div className="channel-idle-content">
                                        <img
                                            className="channel-idle-logo"
                                            src={shell.logo}
                                            alt="Universidad Estatal de Bolívar"
                                            width="360"
                                            height="76"
                                        />
                                        <span className="channel-idle-caption">
                                            TU UNIVERSIDAD. TU SEÑAL.
                                        </span>
                                        <h2>
                                            {data
                                                ? next
                                                    ? "La próxima historia está por comenzar."
                                                    : "Pronto, una nueva historia."
                                                : "Conectando con UEBTV…"}
                                        </h2>
                                        <p>
                                            {next
                                                ? next.title
                                                : "Este es nuestro punto de encuentro. La próxima emisión aparecerá aquí automáticamente."}
                                        </p>
                                        {next ? (<>
                                            <time className="channel-premiere">
                                                {new Date(
                                                    next.starts_at,
                                                ).toLocaleString("es-EC", {
                                                    dateStyle: "medium",
                                                    timeStyle: "short",
                                                })}
                                            </time>
                                            <span className="cv-countdown" aria-live="polite">{untilLabel(next.starts_at, clock)}</span></>
                                        ) : (
                                            data && (
                                                <a
                                                    className="channel-idle-link"
                                                    href={shell.catalog}
                                                >
                                                    Descubre nuestras
                                                    grabaciones{" "}
                                                    <span aria-hidden="true">
                                                        ↗
                                                    </span>
                                                </a>
                                            )
                                        )}
                                    </div>
                                    <div className="channel-idle-footer">
                                        <span>
                                            UNIVERSIDAD ESTATAL DE BOLÍVAR
                                        </span>
                                        <span>Conectamos comunidad.</span>
                                    </div>
                                </div>
                            )}
                        </div>
                    </div>
                    {!tvMode && <div className="channel-now cv-now">
                        <div className="wv-subline">
                            <span className={`wv-state ${live ? "is-live" : ""}`}>{live ? <i className="wv-live-dot" aria-hidden="true" /> : <CvIcon name={advertising ? "megaphone" : playing ? "tv" : "pause"} />}{room.badge}</span>
                            {data?.event?.category && <span className="wv-subline__item"><CvIcon name="tag" />{data.event.category}</span>}
                        </div>
                        <h2 className="wv-title">{data?.event?.title || data?.advertisement?.title || "UEBTV · La señal de nuestra comunidad"}</h2>
                        {room.detail && <p className="cv-now__text">{room.detail}</p>}
                        <div className="wv-actions" role="toolbar" aria-label="Acciones de la señal">
                            {data?.event && <a className="wv-chip" href={data.event.url}><CvIcon name="info" />Información y comentarios</a>}
                            <ShareButton url={`${window.location.origin}/senal`} title="UEBTV · Señal 24/7" label="Compartir la señal" icon={<CvIcon name="share" />} />
                        </div>
                    </div>}
                </section>
                {!tvMode && <section className="wv-more cv-more" aria-labelledby="cv-more-title">
                    <div className="wv-more__heading">
                        <h2 id="cv-more-title">{playing ? "Grabaciones recientes" : "Mientras tanto, mira…"}</h2>
                        <a href={shell.catalog}>Ver todo el catálogo ↗</a>
                    </div>
                    {recent.length ? <div className="wv-more__list">
                        {recent.map((r) => (
                            <a className="wv-card" href={r.url} key={r.id}>
                                <span className="wv-card__thumb" style={r.image ? { backgroundImage: `url(${JSON.stringify(r.image)})` } : undefined}>
                                    {!r.image && <img src={shell.logo} alt="" className="wv-card__logo" />}
                                    <span className="wv-card__tag"><CvIcon name="play" />{statusLabel(r)}</span>
                                </span>
                                <span className="wv-card__body"><strong>{r.title}</strong><small>{r.category} · {r.date}</small></span>
                            </a>
                        ))}
                    </div> : <p className="wv-more__empty">Pronto encontrarás grabaciones de nuestra comunidad.</p>}
                </section>}
                </div>
                {!tvMode && <aside ref={rail} className="channel-rail cv-rail" aria-label="En esta sala">
                    <div className={`wv-side ${collapsed ? "is-collapsed" : ""} ${quietGuide ? "is-quiet" : ""}`}>
                    <div className="wv-panel cv-panel" inert={collapsed}>
                        <div className="wv-tabbar">
                            {chatEvent ? <div className="wv-tabs" role="tablist" aria-label="Panel de la señal">
                                <button type="button" role="tab" id="cv-tab-chat" aria-controls="cv-tabpanel-chat" aria-selected={tab === "chat"} className="wv-tab" onClick={() => setChosenTab("chat")}>
                                    <CvIcon name="chat" />Chat
                                </button>
                                <button type="button" role="tab" id="cv-tab-guide" aria-controls="cv-tabpanel-guide" aria-selected={tab === "guide"} className="wv-tab" onClick={() => setChosenTab("guide")}>
                                    <CvIcon name="calendar" />Programación
                                </button>
                            </div> : <h2 className="cv-panel__title"><CvIcon name="calendar" />Programación</h2>}
                            <button ref={collapseButton} type="button" className="wv-side__toggle" onClick={() => setPanelCollapsed(true)} aria-label="Ocultar panel" title="Ocultar panel">
                                <svg className="wv-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="m6 15 6-6 6 6" /></svg>
                            </button>
                        </div>
                        {chatEvent && <div className="wv-tabpanel" role="tabpanel" id="cv-tabpanel-chat" aria-labelledby="cv-tab-chat" hidden={tab !== "chat"}>
                            <Chat
                                key={chatEvent.id}
                                endpoint={chatEvent.chatUrl}
                                initial={[]}
                                canModerate={false}
                                chatEnabled={chatEvent.chatEnabled}
                                chatRestricted={chatEvent.chatRestricted}
                                isRecording={false}
                                canComment
                            />
                        </div>}
                        <div className="wv-tabpanel" {...(chatEvent ? { role: "tabpanel", "aria-labelledby": "cv-tab-guide" } : {})} id="cv-tabpanel-guide" hidden={tab !== "guide"}>
                            <section className="channel-next cv-guide" aria-label="Próximas emisiones">
                                {data?.event && <div className="cv-guide__now">
                                    <h3>{live ? <i className="wv-live-dot" aria-hidden="true" /> : <CvIcon name="tv" />}Ahora</h3>
                                    <a href={data.event.url}><GuideThumb image={data.event.image} live={live} /><span className="cv-guide__body"><strong>{data.event.title}</strong><small>{room.detail || statusLabel(data.event)}</small></span></a>
                                </div>}
                                <h3>A continuación</h3>
                                <div className="channel-agenda">
                                    {data?.next?.map((item) => (
                                        <article key={item.id} className="channel-agenda-item cv-guide__item">
                                            <GuideThumb image={item.image} />
                                            <div className="cv-guide__body">
                                                <time dateTime={item.starts_at}>{guideTime(item.starts_at)}</time>
                                                <h4>{item.title}</h4><p>{shortDuration(item.duration)} · Canal 24/7</p>
                                            </div>
                                        </article>
                                    ))}
                                    {data && !data.next.length && <div className="cv-panel__empty cv-panel__empty--small">
                                        <CvIcon name="calendar" />
                                        <p><strong>Aún no hay emisiones programadas</strong>Mientras tanto, explora las grabaciones.</p>
                                    </div>}
                                    {!data && <p className="channel-help">Consultando la programación…</p>}
                                </div>
                                <button ref={agendaButton} type="button" className="channel-agenda-open cv-guide__all" onClick={() => setAgendaOpen(true)} aria-haspopup="dialog" aria-expanded={agendaOpen}>
                                    <span className="cv-guide__all-icon" aria-hidden="true"><CvIcon name="calendar" /></span>
                                    <span className="cv-guide__all-text">Ver programación completa</span>
                                    <span className="cv-guide__all-arrow" aria-hidden="true">→</span>
                                </button>
                                <p className="cv-guide__note">Los directos tienen prioridad y pueden desplazar estos horarios.</p>
                            </section>
                        </div>
                    </div>
                    <div className="wv-side__app" inert={!collapsed && !quietGuide}>
                        <button ref={expandButton} type="button" className="wv-side__show" onClick={() => setPanelCollapsed(false)}>
                            <span className="wv-side__show-icon" aria-hidden="true"><CvIcon name={chatEvent ? "chat" : "calendar"} /></span>
                            <span className="wv-side__show-text">{chatEvent ? "Mostrar chat y programación" : "Mostrar programación"}</span>
                            <span className="wv-side__show-arrow" aria-hidden="true"><svg className="wv-icon" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6" /></svg></span>
                        </button>
                        <AppCard />
                    </div>
                    </div>
                </aside>}
            </div>
            {agendaOpen && <AgendaDialog items={data?.agenda || []} onClose={closeAgenda} returnFocusRef={agendaButton} />}
        </div>
    );
}

const time = (date) => date.toLocaleTimeString("es-EC", { hour: "2-digit", minute: "2-digit" });

function guideTime(iso) {
    const date = new Date(iso);
    if (Number.isNaN(date.getTime())) return "";
    const today = new Date(), tomorrow = new Date(); tomorrow.setDate(today.getDate() + 1);
    if (date.toDateString() === today.toDateString()) return `Hoy · ${time(date)}`;
    if (date.toDateString() === tomorrow.toDateString()) return `Mañana · ${time(date)}`;
    return `${date.toLocaleDateString("es-EC", { weekday: "short", day: "numeric", month: "short" })} · ${time(date)}`;
}

function shortDuration(seconds = 0) {
    const minutes = Math.max(1, Math.round(seconds / 60));
    return minutes >= 60 ? `${Math.floor(minutes / 60)} h${minutes % 60 ? ` ${minutes % 60} min` : ""}` : `${minutes} min`;
}

function untilLabel(iso, now) {
    const minutes = Math.round((new Date(iso).getTime() - now) / 60000);
    if (!Number.isFinite(minutes)) return "";
    if (minutes <= 1) return "Comienza en un momento";
    if (minutes < 60) return `Comienza en ${minutes} min`;
    const hours = Math.floor(minutes / 60), rest = minutes % 60;
    if (hours < 24) return `Comienza en ${hours} h${rest ? ` ${rest} min` : ""}`;
    return `Comienza ${guideTime(iso).toLowerCase()}`;
}

/** "En esta sala": qué está pasando en la señal, en palabras simples. */
function roomStatus(data, now) {
    if (!data) return { badge: "Conectando" };
    const next = data.next?.[0];
    if (data.mode === "live") {
        const viewers = data.event?.viewers ?? 0;
        return { detail: `${viewers} ${viewers === 1 ? "espectador" : "espectadores"} conectados${data.resumeTitle ? ` · luego, «${data.resumeTitle}»` : ""}`, badge: "En vivo" };
    }
    if (data.mode === "scheduled") {
        const remaining = Math.max(0, (data.playback?.duration || 0) - (data.playback?.position || 0));
        const ends = new Date(Date.now() + remaining * 1000);
        return { detail: data.playback?.state === "paused" ? "En pausa por un directo" : `Termina a las ${time(ends)}`, badge: "Canal 24/7" };
    }
    if (data.mode === "advertisement") {
        return { detail: next ? `Luego: ${next.title}` : "En breve continúa la programación", badge: "P · Publicidad institucional" };
    }
    return { detail: next ? `Próxima: ${next.title} · ${untilLabel(next.starts_at, now).toLowerCase()}` : "Aún no hay programación anunciada", badge: "Sin emisión" };
}

const CV_ICONS = {
    tv: <><rect x="3" y="6" width="18" height="12" rx="2" /><path d="M8 3l4 3 4-3" /></>,
    megaphone: <path d="M4 10v4h3l7 4V6l-7 4H4Zm13-1.5a4 4 0 0 1 0 7" />,
    pause: <path d="M9 6v12m6-12v12" />,
    tag: <path d="M4 4h7l9 9-7 7-9-9z M8.5 8.5h.01" />,
    refresh: <path d="M20 11a8 8 0 0 0-14.6-4.4L4 8m0-4v4h4m-4 5a8 8 0 0 0 14.6 4.4L20 16m0 4v-4h-4" />,
    info: <><circle cx="12" cy="12" r="8.5" /><path d="M12 11v5m0-8v.1" /></>,
    share: <><circle cx="18" cy="5" r="2" /><circle cx="6" cy="12" r="2" /><circle cx="18" cy="19" r="2" /><path d="m8 11 8-5m-8 7 8 5" /></>,
    play: <path d="M8 5.5v13L18.5 12z" />,
    chat: <path d="M5 6h14v9H9l-4 3z" />,
    calendar: <><rect x="3.5" y="5" width="17" height="15" rx="2.5" /><path d="M8 3v4m8-4v4M3.5 10h17" /></>,
};

function CvIcon({ name }) {
    return <svg className="wv-icon" viewBox="0 0 24 24" aria-hidden="true">{CV_ICONS[name]}</svg>;
}
