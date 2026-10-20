import React, { useEffect, useRef, useState } from "react";
import Player, { BrandedVideo, clockedMediaTarget, isTimeBuffered } from "./Player";
import { useShell } from "./shared";
import UebtvMark from "./UebtvMark";

export function durationLabel(seconds = 0) {
    const s = Math.max(0, Math.floor(seconds));
    return `${Math.floor(s / 3600)} h ${Math.floor((s % 3600) / 60)} min ${s % 60} s`;
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
    const mediaUrl = retry ? `${media.mediaUrl}${media.mediaUrl.includes("?") ? "&" : "?"}reconnect=${retry}` : media.mediaUrl;
    return (
        <>
            <BrandedVideo
                videoRef={video}
                src={mediaUrl}
                mediaTitle={isAd ? `Publicidad institucional · ${media.title}` : snapshot.event.title}
                live={false}
                allowPause={!isAd}
                allowPictureInPicture={!isAd}
                startMuted={startMuted}
                autoStart={false}
                onMuteChange={onMuteChange}
                onLoadedMetadata={() => { lastProgress.current.at = performance.now(); align(true); play(); }}
                onSeeked={() => { lastProgress.current.at = performance.now(); }}
                onUserPause={() => { manualPause.current = true; }}
                onUserResume={() => { manualPause.current = false; autoplayBlocked.current = false; }}
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

export default function Channel({ stateUrl }) {
    const shell = useShell();
    const tvMode = new URLSearchParams(window.location.search).get("modo") === "tv";
    const [data, setData] = useState(null);
    const [received, setReceived] = useState(0);
    const [error, setError] = useState("");
    const [channelMuted, setChannelMuted] = useState(!tvMode);
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
    return (
        <div className={`studio-page channel-page ${tvMode ? "channel-page--tv" : ""}`}>
            <header className="channel-heading">
                <div>
                    <p className="studio-eyebrow">
                        SEÑAL CONTINUA UEBTV
                    </p>
                    <h1>
                        Señal <em>UEBTV.</em>
                    </h1>
                    <p>En vivo y programación automática de la Universidad Estatal de Bolívar.</p>
                </div>
                <div className="channel-heading-actions">
                    <img
                        className="channel-institution-mark"
                        src="/images/brand/ueb-wordmark.png"
                        alt="Universidad Estatal de Bolívar"
                    />
                    <a className="channel-catalog" href={shell.catalog}>
                        Explorar catálogo <span aria-hidden="true">↗</span>
                    </a>
                </div>
            </header>
            {error && (
                <p role="status" className="studio-alert">
                    {error}
                </p>
            )}
            <div className="channel-layout">
                <section className="channel-main" aria-label="Señal de UEBTV">
                    <div className="channel-screen-frame">
                        {!live && <div className="channel-screen-bar">
                            <span>
                                <i /> SEÑAL UEBTV
                                <small>PROGRAMACIÓN CONTINUA</small>
                            </span>
                            <span className="channel-screen-status">
                                {advertising ? "PUBLICIDAD INSTITUCIONAL" : playing ? "PREGRABADO" : data ? "ENTRE EMISIONES" : "CONECTANDO"}
                            </span>
                        </div>}
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
                                        {next ? (
                                            <time className="channel-premiere">
                                                {new Date(
                                                    next.starts_at,
                                                ).toLocaleString("es-EC", {
                                                    dateStyle: "medium",
                                                    timeStyle: "short",
                                                })}
                                            </time>
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
                    <div className="channel-now">
                        {!live && <div className="channel-now-top">
                            <span
                                className={`channel-badge ${live ? "channel-live" : ""}`}
                            >
                                {live
                                    ? "● En directo"
                                    : advertising
                                      ? "P · Publicidad institucional"
                                      : playing
                                      ? "Pregrabado · Emisión sincronizada"
                                      : "Nuestra señal"}
                            </span>
                            <span className="channel-auto">
                                ↻ Programación automática
                            </span>
                        </div>}
                        <h2>
                            {data?.event?.title || data?.advertisement?.title ||
                                "Un espacio para encontrarnos"}
                        </h2>
                        <p>
                            {live
                                ? data.resumeTitle ? `Al finalizar esta transmisión, continuamos con «${data.resumeTitle}».` : "Al finalizar esta transmisión, la programación continuará automáticamente."
                                : advertising
                                  ? "Mensaje institucional de la Universidad Estatal de Bolívar. Al comenzar una emisión, la señal continuará automáticamente."
                                  : playing
                                  ? "Estás viendo el mismo momento que nuestra comunidad. Al terminar, continuamos con la siguiente emisión."
                                  : "Conferencias, encuentros y momentos de la UEB. En directo cuando sucede; disponibles para volver a disfrutarlos."}
                        </p>
                        {data?.event && (
                            <a
                                className="studio-text-link"
                                href={data.event.url}
                            >
                                Información y comentarios ↗
                            </a>
                        )}
                    </div>
                </section>
                <div className="channel-rail">
                    {!tvMode && <section className="channel-app-card" aria-labelledby="channel-app-title">
                        <div className="channel-app-art">
                            <div className="channel-app-art-inner">
                                <img className="channel-app-mascot" src="/images/download/zorrito-qr-gafas.webp" alt="" width="960" height="800" loading="lazy" />
                                <div className="channel-app-qr">
                                    <img src="/images/download/uebtv-apk-qr.png" alt="Código QR para descargar UEBTV" width="400" height="400" />
                                </div>
                            </div>
                        </div>
                        <div className="channel-app-copy">
                            <p className="channel-app-eyebrow">UEBTV EN TU PANTALLA</p>
                            <h2 id="channel-app-title">Lleva la señal <span>contigo.</span></h2>
                            <p>Escanea el QR desde otro dispositivo o descarga el APK aquí.</p>
                            <a className="channel-app-download" href="/descargas/uebtv.apk" download>Descargar APK <span aria-hidden="true">↗</span></a>
                            <small>Para Android y Android TV.</small>
                        </div>
                    </section>}
                <aside className="channel-next" aria-label="Próximas emisiones">
                    <div className="channel-next-heading">
                        <p className="studio-eyebrow">LA PROGRAMACIÓN</p>
                        <h2>
                            A continuación
                            <span>{data?.next?.length || "—"}</span>
                        </h2>
                        <p>Las próximas historias de nuestra comunidad.</p>
                    </div>
                    <div className="channel-agenda">
                        {data?.next?.map((item, index) => (
                            <article
                                key={item.id}
                                className="channel-agenda-item"
                            >
                                <span className="channel-agenda-number">
                                    {String(index + 1).padStart(2, "0")}
                                </span>
                                <div>
                                    <time>
                                        {new Date(
                                            item.starts_at,
                                        ).toLocaleString("es-EC", {
                                            dateStyle: "short",
                                            timeStyle: "short",
                                        })}
                                    </time>
                                    <h3>{item.title}</h3>
                                    <p>
                                        {durationLabel(item.duration)} ·
                                        Pregrabado
                                    </p>
                                </div>
                            </article>
                        ))}
                        {data && !data.next.length && (
                            <div className="channel-agenda-empty">
                                <svg
                                    aria-hidden="true"
                                    width="36"
                                    height="36"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    strokeWidth="1.2"
                                >
                                    <rect
                                        x="3"
                                        y="5"
                                        width="18"
                                        height="16"
                                        rx="3"
                                    />
                                    <path d="M7 3v4m10-4v4M3 11h18m-13 5h3m2 0h3" />
                                </svg>
                                <h3>Más encuentros por venir</h3>
                                <p>
                                    Estamos preparando la siguiente
                                    programación. Mientras tanto, explora los
                                    contenidos disponibles.
                                </p>
                                <a href={shell.catalog}>
                                    Ver contenidos{" "}
                                    <span aria-hidden="true">↗</span>
                                </a>
                            </div>
                        )}
                        {!data && (
                            <p className="channel-help">
                                Consultando la programación…
                            </p>
                        )}
                    </div>
                    <div className="channel-total">
                        <small>
                            {data?.summary?.remainingSeconds
                                ? "TIEMPO POR COMPARTIR"
                                : "SIGAMOS CONECTADOS"}
                        </small>
                        <strong>
                            {data?.summary?.remainingSeconds
                                ? durationLabel(data.summary.remainingSeconds)
                                : "Tu próxima historia, aquí."}
                        </strong>
                        <p>
                            {data?.summary?.remainingSeconds
                                ? "De contenido programado para ti."
                                : "La señal se actualizará automáticamente."}
                        </p>
                    </div>
                    <p className="channel-agenda-note">
                        Los encuentros en directo tienen prioridad y pueden
                        desplazar estos horarios.
                    </p>
                </aside>
                </div>
            </div>
        </div>
    );
}
