import React, { useEffect, useRef, useState } from "react";
import PlayerWatermark from "./PlayerWatermark";

const timeLabel = (seconds) => {
    if (!Number.isFinite(seconds) || seconds < 0) return "0:00";
    const value = Math.floor(seconds), hours = Math.floor(value / 3600), minutes = Math.floor((value % 3600) / 60), remaining = value % 60;
    return hours ? `${hours}:${String(minutes).padStart(2, "0")}:${String(remaining).padStart(2, "0")}` : `${minutes}:${String(remaining).padStart(2, "0")}`;
};

// HLS keeps a small buffer at the live edge. Treat that normal latency as live;
// controls only announce a delay after the viewer has genuinely time-shifted.
const LIVE_EDGE_OFFSET = 3;
const LIVE_REWIND_THRESHOLD = 5;

export const clockedMediaTarget = (video, position, duration) => {
    const mediaEnd = Number.isFinite(video.duration) ? video.duration : duration;
    return Math.max(0, Math.min(position, duration, mediaEnd - 0.25));
};

export const isTimeBuffered = (video, time) => {
    for (let i = 0; i < video.buffered.length; i++) {
        if (video.buffered.start(i) - 0.25 <= time && time < video.buffered.end(i) - 0.25) return true;
    }
    return false;
};

function PlayerIcon({ name }) {
    const paths = {
        play: <path d="M8 5.6v12.8L18.4 12 8 5.6Z" />,
        pause: <><path d="M7.5 5.5h3v13h-3zM13.5 5.5h3v13h-3z" /></>,
        volume: <><path d="M4 10h3.8l4-3.2v10.4L7.8 14H4z" /><path d="M15 9a4.2 4.2 0 0 1 0 6M17.3 6.7a7.4 7.4 0 0 1 0 10.6" /></>,
        muted: <><path d="M4 10h3.8l4-3.2v10.4L7.8 14H4z" /><path d="m15 10 5 5m0-5-5 5" /></>,
        fullscreen: <path d="M5 9V5h4M15 5h4v4M19 15v4h-4M9 19H5v-4" />,
        pip: <><rect x="3.5" y="5" width="17" height="14" rx="1.5" /><rect x="12" y="12" width="6" height="4.5" rx=".6" /></>,
    };
    return <svg className="uebtv-player-icon-svg" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" strokeWidth="1.9" strokeLinecap="round" strokeLinejoin="round">{paths[name]}</svg>;
}

export function BrandedVideo({ videoRef, src, poster, mediaTitle, live = false, dvr = false, seekable = false, hidden = false, startMuted = live, autoStart = live || startMuted, onMuteChange, onLoadedMetadata, onSeeking, onSeeked, onPlay, onPlaying, onPause, onWaiting, onEnded, onUserPause, onUserResume, onTimeUpdate, onError, children }) {
    const shell = useRef(null), playbackChanging = useRef(false), autoplayAttempted = useRef(false), controlsTimer = useRef(), bufferTimer = useRef(), slowBufferTimer = useRef();
    const [playing, setPlaying] = useState(false), [autoplayBlocked, setAutoplayBlocked] = useState(false), [controlsVisible, setControlsVisible] = useState(true), [current, setCurrent] = useState(0), [duration, setDuration] = useState(0), [liveWindow, setLiveWindow] = useState({ start: 0, end: 0 }), [volume, setVolume] = useState(1), [muted, setMuted] = useState(startMuted), [rate, setRate] = useState(1), [inPictureInPicture, setInPictureInPicture] = useState(false), [isFullscreen, setIsFullscreen] = useState(false);
    const [buffering, setBuffering] = useState(0);
    const stopBuffering = () => {
        window.clearTimeout(bufferTimer.current);
        window.clearTimeout(slowBufferTimer.current);
        setBuffering(0);
    };
    const waitForData = (event) => {
        const element = event.currentTarget;
        if (hidden || element.paused || !element.currentSrc || bufferTimer.current) return;
        bufferTimer.current = window.setTimeout(() => {
            bufferTimer.current = undefined;
            if (element.paused || element.readyState >= 3) return;
            setBuffering(1);
            slowBufferTimer.current = window.setTimeout(() => setBuffering(2), 3000);
        }, 450);
    };
    const video = () => videoRef.current;
    const updateProgress = () => { const v = video(); if (!v) return; setCurrent(v.currentTime || 0); setDuration(Number.isFinite(v.duration) ? v.duration : 0); if (dvr && v.seekable.length) setLiveWindow({ start: v.seekable.start(v.seekable.length - 1), end: v.seekable.end(v.seekable.length - 1) }); };
    const hideControlsSoon = () => {
        window.clearTimeout(controlsTimer.current);
        if (playing) controlsTimer.current = window.setTimeout(() => setControlsVisible(false), 2200);
    };
    const showControls = () => {
        setControlsVisible(true);
        hideControlsSoon();
    };
    const togglePlay = () => {
        const v = video();
        if (!v || playbackChanging.current) return;
        showControls();
        playbackChanging.current = true;
        try { if (v.paused) { onUserResume?.(); v.play().catch(() => {}); } else { onUserPause?.(); v.pause(); } }
        finally {
            // No esperamos la promesa de play(): durante una recarga HLS puede
            // quedar pendiente y antes dejaba bloqueado el botón.
            window.setTimeout(() => { playbackChanging.current = false; }, 250);
        }
    };
    const setProgress = (event) => { const v = video(); if (!v || !(seekable || dvr)) return; v.currentTime = Number(event.target.value); setCurrent(v.currentTime); };
    const seekBy = (seconds) => {
        if (!(seekable || dvr)) return false;
        const v = video();
        if (!v || v.readyState < 1) return false;
        const range = v.seekable;
        const start = dvr && range.length ? range.start(range.length - 1) : 0;
        const end = dvr && range.length ? range.end(range.length - 1) : v.duration;
        if (!Number.isFinite(end) || end <= start) return false;
        v.currentTime = Math.max(start, Math.min(v.currentTime + seconds, end - 0.25));
        setCurrent(v.currentTime);
        showControls();
        return true;
    };
    const onTimelineKeyDown = (event) => {
        if (event.key === "ArrowLeft" && seekBy(-5)) event.preventDefault();
        if (event.key === "ArrowRight" && seekBy(5)) event.preventDefault();
    };
    const setAudio = (event) => { const v = video(), next = Number(event.target.value); if (!v) return; v.volume = next; v.muted = next === 0; setVolume(next); setMuted(next === 0); };
    const toggleMute = () => { const v = video(); if (!v) return; v.muted = !v.muted; setMuted(v.muted); };
    const changeRate = (event) => { const v = video(), next = Number(event.target.value); if (!v) return; v.playbackRate = next; setRate(next); };
    const fullscreen = () => {
        const action = document.fullscreenElement ? document.exitFullscreen?.() : shell.current?.requestFullscreen?.();
        action?.catch?.(() => {});
    };
    const pictureInPicture = async () => { const v = video(); if (!v || !document.pictureInPictureEnabled) return; try { document.pictureInPictureElement ? await document.exitPictureInPicture() : await v.requestPictureInPicture(); } catch {} };
    useEffect(() => { const v = video(); if (!v) return; const entered = () => setInPictureInPicture(true), left = () => setInPictureInPicture(false); v.addEventListener("enterpictureinpicture", entered); v.addEventListener("leavepictureinpicture", left); return () => { v.removeEventListener("enterpictureinpicture", entered); v.removeEventListener("leavepictureinpicture", left); }; }, [videoRef]);
    useEffect(() => () => { window.clearTimeout(bufferTimer.current); window.clearTimeout(slowBufferTimer.current); }, []);
    useEffect(() => { const changed = () => setIsFullscreen(document.fullscreenElement === shell.current); document.addEventListener("fullscreenchange", changed); return () => document.removeEventListener("fullscreenchange", changed); }, []);
    const goLive = () => { const v = video(); if (!v) return; onUserResume?.(); const ranges = v.seekable; if (ranges.length) v.currentTime = Math.max(ranges.start(ranges.length - 1), ranges.end(ranges.length - 1) - LIVE_EDGE_OFFSET); v.play().catch(() => {}); };
    const attemptLiveAutoplay = (element) => {
        if (!autoStart || autoplayAttempted.current) return;
        autoplayAttempted.current = true;
        if (startMuted) {
            element.muted = true;
            element.defaultMuted = true;
            setMuted(true);
        }
        element.play().catch(async (error) => {
            if (error.name !== "NotAllowedError") return;
            if (!element.muted) {
                element.muted = true;
                try { await element.play(); return; } catch { /* A tap is required. */ }
            }
            setAutoplayBlocked(true);
        });
    };
    const canPictureInPicture = typeof document !== "undefined" && document.pictureInPictureEnabled;
    const hasDvr = dvr && liveWindow.end - liveWindow.start > 2;
    const behindLive = hasDvr && liveWindow.end - current > LIVE_REWIND_THRESHOLD;
    const displayedLivePosition = behindLive ? current : liveWindow.end;
    useEffect(() => {
        if (!dvr) return undefined;
        const timer = window.setInterval(updateProgress, 1000);
        return () => window.clearInterval(timer);
    }, [dvr]);
    useEffect(() => {
        window.clearTimeout(controlsTimer.current);
        if (!playing) { setControlsVisible(true); return undefined; }
        // Los controles no quedan fijados al atrasarse: el botón de directo se
        // muestra automáticamente cuando el usuario vuelve a tocarlos.
        controlsTimer.current = window.setTimeout(() => setControlsVisible(false), 2200);
        return () => window.clearTimeout(controlsTimer.current);
    }, [playing]);
    useEffect(() => {
        const onKeyDown = (event) => {
            if (hidden || event.defaultPrevented || event.altKey || event.ctrlKey || event.metaKey || !shell.current) return;
            // Only the visible player owns page-level shortcuts. Native keyboard
            // behavior must remain available in forms, links and player controls.
            if (document.querySelector(".uebtv-player-shell:not(.is-hidden)") !== shell.current) return;
            if (event.key === "Escape" && document.fullscreenElement === shell.current) {
                event.preventDefault();
                fullscreen();
                return;
            }
            const key = event.key.toLowerCase();
            const target = event.target instanceof Element ? event.target : null;
            if (target?.closest('input, textarea, select, [contenteditable]:not([contenteditable="false"]), [role="textbox"], [role="combobox"], [role="slider"]')) return;
            const interactive = target?.closest("button, a, summary");
            if (interactive && (!shell.current.contains(interactive) || key === " " || key === "spacebar")) return;
            if (key === "f" && !event.repeat) {
                event.preventDefault();
                fullscreen();
            } else if ((key === " " || key === "spacebar") && !event.repeat) {
                event.preventDefault();
                togglePlay();
            } else if (key === "arrowleft" || key === "arrowright") {
                if (seekBy(key === "arrowright" ? 5 : -5)) event.preventDefault();
            }
        };
        document.addEventListener("keydown", onKeyDown);
        return () => document.removeEventListener("keydown", onKeyDown);
    }, [hidden, seekable, dvr, playing]);
    return <div ref={shell} className={`uebtv-player-shell ${hidden ? "is-hidden" : ""} ${controlsVisible ? "is-chrome-visible" : ""}`} onPointerMove={showControls} onPointerDown={showControls}>
        <video ref={videoRef} className="uebtv-video" src={src} poster={poster} defaultMuted={startMuted} autoPlay={autoStart} playsInline preload="metadata" disableRemotePlayback hidden={hidden}
            onCanPlay={(event) => attemptLiveAutoplay(event.currentTarget)} onLoadedMetadata={(event) => { attemptLiveAutoplay(event.currentTarget); updateProgress(); onLoadedMetadata?.(event); }} onTimeUpdate={(event) => { updateProgress(); onTimeUpdate?.(event); }} onDurationChange={updateProgress}
            onVolumeChange={(event) => { setVolume(event.currentTarget.volume); setMuted(event.currentTarget.muted); onMuteChange?.(event.currentTarget.muted || event.currentTarget.volume === 0); }}
            onPlay={(event) => { playbackChanging.current = false; setPlaying(true); setAutoplayBlocked(false); onPlay?.(event); }} onPlaying={(event) => { stopBuffering(); onPlaying?.(event); }} onPause={(event) => { playbackChanging.current = false; setPlaying(false); stopBuffering(); onPause?.(event); }} onWaiting={(event) => { waitForData(event); onWaiting?.(event); }} onStalled={waitForData} onSeeking={onSeeking} onSeeked={onSeeked} onEnded={(event) => { stopBuffering(); onEnded?.(event); }} onError={(event) => { stopBuffering(); onError?.(event); }} />
        {!hidden && <>
            <PlayerWatermark />
            {live && <span className="uebtv-player-live">● EN VIVO</span>}
            {isFullscreen && mediaTitle && <span className={`uebtv-player-title ${live ? "is-live" : ""}`} title={mediaTitle}>{mediaTitle}</span>}
            {inPictureInPicture && <button type="button" className="uebtv-player-pip-return" onClick={pictureInPicture}>
                <PlayerIcon name="pip" />
                Volver al reproductor
            </button>}
            {live && autoplayBlocked && <div className="react-player-notice" role="status"><p>Tu navegador bloqueó la reproducción automática. Toca reproducir para continuar.</p><button className="studio-button" onClick={togglePlay}>Reproducir</button></div>}
            {buffering > 0 && !autoplayBlocked && <div className="uebtv-player-buffering" role="status" aria-live="polite">
                <span className="uebtv-player-buffering__wheel" aria-hidden="true" />
                <span>{buffering === 2 ? "La señal tarda en llegar. Intentando continuar…" : "Cargando video…"}</span>
                {buffering === 2 && <small>Si persiste, revisa tu conexión.</small>}
            </div>}
            <div className={`uebtv-player-controls ${live ? "is-live-controls" : ""}`} aria-label="Controles del reproductor">
                <div className={`uebtv-player-timeline ${seekable ? "is-vod-timeline" : !hasDvr ? "is-signal-only" : ""}`}>{seekable ? <><input aria-label="Posición del video" title="Flechas izquierda/derecha: saltar 5 segundos" className="uebtv-player-range uebtv-player-range--progress" type="range" min="0" max={duration || 0} step="0.1" value={Math.min(current, duration || 0)} onChange={setProgress} onKeyDown={onTimelineKeyDown} style={{ "--player-progress": `${duration ? (current / duration) * 100 : 0}%` }} /><span>{timeLabel(current)} / {timeLabel(duration)}</span></> : hasDvr ? <><input aria-label="Retroceder en la transmisión en vivo" title="Flechas izquierda/derecha: saltar 5 segundos" className="uebtv-player-range uebtv-player-range--progress uebtv-player-range--live" type="range" min={liveWindow.start} max={liveWindow.end} step="0.1" value={Math.min(Math.max(displayedLivePosition, liveWindow.start), liveWindow.end)} onChange={setProgress} onKeyDown={onTimelineKeyDown} /><span className={`uebtv-player-live-position ${behindLive ? "is-behind" : ""}`}>{behindLive ? `Hace ${Math.ceil(liveWindow.end - current)} s` : "EN DIRECTO"}</span></> : <><span className="uebtv-player-live-static" aria-hidden="true"><i /></span><span className="uebtv-player-now">{live ? "EN DIRECTO" : "EMISIÓN SINCRONIZADA"}</span></>}</div>
                <div className="uebtv-player-actions">
                    <button type="button" className="uebtv-player-icon-button uebtv-player-icon-button--primary" onClick={togglePlay} aria-label={playing ? "Pausar" : "Reproducir"} title={playing ? "Pausar (Espacio)" : "Reproducir (Espacio)"}><PlayerIcon name={playing ? "pause" : "play"} /></button>
                    <button type="button" className="uebtv-player-icon-button" onClick={toggleMute} aria-label={muted ? "Activar sonido" : "Silenciar"} title={muted ? "Activar sonido" : "Silenciar"}><PlayerIcon name={muted || volume === 0 ? "muted" : "volume"} /></button>
                    <input aria-label="Volumen" className="uebtv-player-range uebtv-player-range--volume" type="range" min="0" max="1" step="0.05" value={muted ? 0 : volume} onChange={setAudio} style={{ "--player-progress": `${(muted ? 0 : volume) * 100}%` }} />
                    {seekable && <select className="uebtv-player-speed" value={rate} onChange={changeRate} aria-label="Velocidad de reproducción"><option value="0.75">0.75×</option><option value="1">1×</option><option value="1.25">1.25×</option><option value="1.5">1.5×</option><option value="2">2×</option></select>}
                    <span className="uebtv-player-actions-spacer" />
                    {behindLive && <button type="button" className="uebtv-player-go-live" onClick={goLive} title="Ir al momento actual de la transmisión"><i aria-hidden="true" />Volver al directo</button>}
                    {canPictureInPicture && <button type="button" className={`uebtv-player-icon-button ${inPictureInPicture ? "is-active" : ""}`} onClick={pictureInPicture} aria-label={inPictureInPicture ? "Volver al reproductor" : "Imagen en imagen"} title={inPictureInPicture ? "Volver al reproductor" : "Imagen en imagen"}><PlayerIcon name="pip" /></button>}
                    <button type="button" className="uebtv-player-icon-button" onClick={fullscreen} aria-label="Pantalla completa" title="Pantalla completa (F); salir con Esc"><PlayerIcon name="fullscreen" /></button>
                </div>
            </div>
            {children}
        </>}
    </div>;
}

export function ScheduledPlayer({ scheduled, poster, onState, onVideoProgress }) {
    const video = useRef(), snapshot = useRef(), received = useRef(0), previous = useRef();
    const lastSeekAt = useRef(0), requestedTarget = useRef(null), externalSeek = useRef(false);
    const [state, setState] = useState("loading"), [notice, setNotice] = useState("Consultando el horario de emisión…"), [play, setPlay] = useState(false);
    const playable = ["playing", "ended"].includes(state);
    const align = (force = false) => {
        const s = snapshot.current, v = video.current;
        if (s?.state !== "playing" || !v || v.readyState < 1 || v.seeking) return;
        const now = performance.now();
        const target = clockedMediaTarget(v, s.position + (now - received.current) / 1000, s.duration);
        const drift = Math.abs(v.currentTime - target);
        if ((force && drift > 1.5) || (!force && drift > 10 && v.readyState >= 3 && now - lastSeekAt.current > 15000 && isTimeBuffered(v, target))) {
            lastSeekAt.current = now;
            requestedTarget.current = target;
            v.currentTime = target;
        }
        if (v.playbackRate !== 1) v.playbackRate = 1;
    };
    const start = async () => { align(true); try { await video.current.play(); setNotice(""); setPlay(false); } catch { setNotice("Pulsa reproducir para unirte a la emisión."); setPlay(true); } };
    useEffect(() => {
        let stopped = false, timer, controller, working = false;
        async function sync() {
            if (stopped || working || document.hidden) return;
            working = true; controller = new AbortController(); const timeout = setTimeout(() => controller.abort(), 8000);
            try {
                const began = performance.now(), response = await fetch(scheduled.stateUrl, { cache: "no-store", signal: controller.signal, headers: { Accept: "application/json" } });
                if (!response.ok) throw Error(); const data = await response.json(); if (stopped) return;
                received.current = performance.now(); snapshot.current = { ...data, position: data.position + (received.current - began) / 2000 }; setState(data.state); onState?.(data.state);
                setNotice((value) => value === "No se pudo actualizar el horario. Reconectando…" ? "" : value);
                if (["upcoming", "cancelled", "paused", "processing", "failed"].includes(data.state)) { video.current?.pause(); setNotice(data.state === "processing" ? "Estamos optimizando el video. Estará disponible cuando termine la preparación." : data.state === "failed" ? "No se pudo preparar el video. El administrador puede reintentar la conversión." : data.state === "paused" ? "Programación en pausa por una transmisión en directo. Continuaremos desde este punto." : data.state === "cancelled" ? "Esta emisión fue cancelada." : `Pregrabado · Comienza el ${new Date(data.starts_at).toLocaleString("es-EC")}`); setPlay(false); }
                else if (data.state === "ended") { setNotice(""); setPlay(false); if (previous.current === "playing" && video.current) { video.current.pause(); video.current.currentTime = 0; } }
                else { align(); if (previous.current !== "playing") { setNotice("Pulsa reproducir para unirte a la emisión."); setPlay(true); } }
                previous.current = data.state;
            } catch { if (!stopped) setNotice("No se pudo actualizar el horario. Reconectando…"); }
            finally { clearTimeout(timeout); working = false; if (!stopped) timer = setTimeout(sync, 5000); }
        }
        const visible = () => { clearTimeout(timer); sync(); }; sync(); document.addEventListener("visibilitychange", visible);
        return () => { stopped = true; clearTimeout(timer); controller?.abort(); document.removeEventListener("visibilitychange", visible); };
    }, [scheduled.stateUrl, onState]);
    return <><BrandedVideo videoRef={video} hidden={!playable} src={playable ? scheduled.mediaUrl : undefined} poster={poster || undefined} mediaTitle={scheduled.title} live={state === "playing"} autoStart={false} seekable={state === "ended"} onLoadedMetadata={() => { if (snapshot.current?.state === "playing") start(); }} onSeeking={() => {
        const v = video.current, s = snapshot.current;
        if (s?.state !== "playing" || requestedTarget.current === null || !v) return;
        const target = clockedMediaTarget(v, s.position + (performance.now() - received.current) / 1000, s.duration);
        if (Math.abs(v.currentTime - requestedTarget.current) > 1.5 && Math.abs(v.currentTime - target) > 8) externalSeek.current = true;
    }} onSeeked={() => { if (externalSeek.current) { externalSeek.current = false; align(true); } }} onPlay={() => { setNotice(""); setPlay(false); }} onPause={() => { if (snapshot.current?.state === "playing") { setNotice("Reanudar te llevará al momento actual de la emisión."); setPlay(true); } }} onTimeUpdate={onVideoProgress} onError={() => setNotice("No se pudo cargar el video. Comprueba tu conexión y vuelve a abrir la sala.")} /><span className="react-player-label">{state === "ended" ? "Pregrabado · Disponible bajo demanda" : "Emisión programada · Pregrabado"}</span>{notice && <div className="react-player-notice" role="status"><span aria-hidden="true">▷</span><p>{notice}</p>{play && <button className="studio-button" onClick={start}>Reproducir</button>}</div>}</>;
}

export default function Player({ event: e, onScheduledState, recordingRef, onVideoProgress, startMuted, onMuteChange }) {
    const video = useRef(); const manualPause = useRef(false), retryAttempts = useRef(0); const [error, setError] = useState(""); const [retry, setRetry] = useState(0); const live = e.status === "en_vivo" && !e.scheduled;
    useEffect(() => {
        if (!live || !e.hlsUrl) return;
        let hls, stopped = false, reconnectTimer, lastProgress = performance.now(), lastPosition = 0, mediaRecovered = false, rebufferAttempted = false;
        const target = video.current;
        setError("");
        const reconnect = () => {
            if (stopped || reconnectTimer || manualPause.current) return;
            const delay = Math.min(1000 * 2 ** Math.min(retryAttempts.current++, 3), 8000);
            setError("La señal se interrumpió. Reconectando automáticamente…");
            reconnectTimer = window.setTimeout(() => setRetry((value) => value + 1), delay);
        };
        const progress = () => {
            if (Math.abs(target.currentTime - lastPosition) > 0.05) { lastPosition = target.currentTime; lastProgress = performance.now(); rebufferAttempted = false; retryAttempts.current = 0; setError(""); }
        };
        const playing = () => { lastProgress = performance.now(); mediaRecovered = false; setError(""); };
        const online = () => { if (!manualPause.current && target.readyState < 3) reconnect(); };
        const visible = () => {
            if (document.hidden || manualPause.current || performance.now() - lastProgress < 15000) return;
            lastProgress = performance.now();
            rebufferAttempted = false;
            hls?.startLoad();
        };
        const nativeError = () => reconnect();
        target.addEventListener("timeupdate", progress);
        target.addEventListener("playing", playing);
        target.addEventListener("error", nativeError);
        window.addEventListener("online", online);
        document.addEventListener("visibilitychange", visible);
        const watchdog = window.setInterval(() => {
            if (document.hidden || target.paused || manualPause.current) return;
            const idle = performance.now() - lastProgress;
            // A live HLS fragment can arrive late on a busy/mobile network.
            // First resume loading; rebuilding the whole player is a last resort.
            if (idle > 20000 && !rebufferAttempted && hls) {
                rebufferAttempted = true;
                hls.startLoad();
            }
            if (idle > 35000) reconnect();
        }, 2000);
        async function attach() {
            try {
                if (target.canPlayType("application/vnd.apple.mpegurl")) {
                    const url = new URL(e.hlsUrl, window.location.href);
                    url.searchParams.set("reconnect", String(retry));
                    target.src = url.toString();
                    if (!manualPause.current) target.play().catch(() => {});
                    return;
                }
                const { default: Hls } = await import("hls.js");
                if (stopped) return;
                if (!Hls.isSupported()) { setError("Este navegador no admite la emisión."); return; }
                hls = new Hls({ lowLatencyMode: false, liveSyncDurationCount: 3, liveMaxLatencyDurationCount: 6, maxLiveSyncPlaybackRate: 1.15, maxBufferLength: 12, maxMaxBufferLength: 20, backBufferLength: 12 });
                hls.loadSource(e.hlsUrl);
                hls.attachMedia(target);
                hls.on(Hls.Events.MANIFEST_PARSED, () => { if (!manualPause.current) target.play().catch(() => {}); });
                hls.on(Hls.Events.ERROR, (_, data) => {
                    if (!data.fatal) return;
                    if (data.type === Hls.ErrorTypes.MEDIA_ERROR && !mediaRecovered) {
                        mediaRecovered = true;
                        hls.recoverMediaError();
                    } else reconnect();
                });
            } catch { if (!stopped) reconnect(); }
        }
        attach();
        return () => {
            stopped = true;
            window.clearTimeout(reconnectTimer);
            window.clearInterval(watchdog);
            window.removeEventListener("online", online);
            document.removeEventListener("visibilitychange", visible);
            target.removeEventListener("timeupdate", progress);
            target.removeEventListener("playing", playing);
            target.removeEventListener("error", nativeError);
            hls?.destroy();
            target.removeAttribute("src");
            target.load();
        };
    }, [e.hlsUrl, live, retry]);
    if (e.scheduled) return <ScheduledPlayer scheduled={e.scheduled} poster={e.image} onState={onScheduledState} onVideoProgress={onVideoProgress} />;
    if (live && e.hlsUrl) return <BrandedVideo videoRef={video} mediaTitle={e.title} live dvr startMuted={startMuted ?? true} onTimeUpdate={onVideoProgress} onMuteChange={onMuteChange} onUserPause={() => { manualPause.current = true; }} onUserResume={() => { manualPause.current = false; }} onPlay={() => { manualPause.current = false; }}>{error && <div className="react-player-notice" role="status"><p>{error}</p><button className="studio-button" onClick={() => { manualPause.current = false; retryAttempts.current = 0; setRetry((value) => value + 1); }}>Reconectar ahora</button></div>}</BrandedVideo>;
    if (e.videoUrl) return <BrandedVideo videoRef={recordingRef} src={e.videoUrl} poster={e.image || undefined} mediaTitle={e.title} seekable onTimeUpdate={onVideoProgress} onSeeked={onVideoProgress} />;
    return <div className="uebtv-player-empty"><span className="uebtv-player-empty__icon">▶</span><h2>{e.status === "finalizado" ? "Transmisión finalizada" : "Video próximamente"}</h2><p>{e.status === "finalizado" ? ["waiting", "processing", "recording"].includes(e.recordingStatus) ? "Estamos preparando la grabación. Estará disponible aquí automáticamente." : "La grabación todavía no está disponible." : "Esta sala se habilitará cuando comience la transmisión."}</p></div>;
}
