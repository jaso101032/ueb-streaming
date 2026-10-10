import React, { useEffect, useRef, useState } from "react";
export function ScheduledPlayer({ scheduled, poster, onState }) {
    const video = useRef(),
        snapshot = useRef(),
        received = useRef(0),
        previous = useRef(),
        [state, setState] = useState("loading"),
        [notice, setNotice] = useState("Consultando el horario de emisión…"),
        [play, setPlay] = useState(false);
    const align = () => {
        const s = snapshot.current,
            v = video.current;
        if (s?.state !== "playing" || !v || v.readyState < 1) return;
        const target = Math.min(
            s.duration,
            s.position + (performance.now() - received.current) / 1000,
        );
        if (Math.abs(v.currentTime - target) > 1.5) v.currentTime = target;
        v.playbackRate = 1;
    };
    const start = async () => {
        align();
        try {
            await video.current.play();
            setNotice("");
            setPlay(false);
        } catch {
            setNotice("Pulsa reproducir para unirte a la emisión.");
            setPlay(true);
        }
    };
    useEffect(() => {
        let stopped = false,
            timer,
            controller,
            working = false;
        async function sync() {
            if (stopped || working || document.hidden) return;
            working = true;
            controller = new AbortController();
            const timeout = setTimeout(() => controller.abort(), 8000);
            try {
                const began = performance.now(),
                    r = await fetch(scheduled.stateUrl, {
                        cache: "no-store",
                        signal: controller.signal,
                        headers: { Accept: "application/json" },
                    });
                if (!r.ok) throw Error();
                const d = await r.json();
                if (stopped) return;
                received.current = performance.now();
                snapshot.current = {
                    ...d,
                    position: d.position + (received.current - began) / 2000,
                };
                setState(d.state);
                onState?.(d.state);
                setNotice((n) =>
                    n === "No se pudo actualizar el horario. Reconectando…"
                        ? ""
                        : n,
                );
                if (
                    d.state === "upcoming" ||
                    d.state === "cancelled" ||
                    d.state === "paused" ||
                    d.state === "processing" ||
                    d.state === "failed"
                ) {
                    video.current?.pause();
                    setNotice(
                        d.state === "processing"
                            ? "Estamos optimizando el video. Estará disponible cuando termine la preparación."
                            : d.state === "failed"
                              ? "No se pudo preparar el video. El administrador puede reintentar la conversión."
                              : d.state === "paused"
                                ? "Programación en pausa por una transmisión en directo. Continuaremos desde este punto."
                                : d.state === "cancelled"
                                  ? "Esta emisión fue cancelada."
                                  : `Pregrabado · Comienza el ${new Date(d.starts_at).toLocaleString("es-EC")}`,
                    );
                    setPlay(false);
                } else if (d.state === "ended") {
                    setNotice("");
                    setPlay(false);
                    if (previous.current === "playing" && video.current) {
                        video.current.pause();
                        video.current.currentTime = 0;
                    }
                } else {
                    align();
                    if (previous.current !== "playing") {
                        setNotice("Pulsa reproducir para unirte a la emisión.");
                        setPlay(true);
                    }
                }
                previous.current = d.state;
            } catch {
                if (!stopped)
                    setNotice(
                        "No se pudo actualizar el horario. Reconectando…",
                    );
            } finally {
                clearTimeout(timeout);
                working = false;
                if (!stopped) timer = setTimeout(sync, 5000);
            }
        }
        const visible = () => {
            clearTimeout(timer);
            sync();
        };
        sync();
        document.addEventListener("visibilitychange", visible);
        return () => {
            stopped = true;
            clearTimeout(timer);
            controller?.abort();
            document.removeEventListener("visibilitychange", visible);
        };
    }, [scheduled.stateUrl, onState]);
    return (
        <>
            <video
                ref={video}
                className="uebtv-video"
                hidden={!["playing", "ended"].includes(state)}
                src={
                    ["playing", "ended"].includes(state)
                        ? scheduled.mediaUrl
                        : undefined
                }
                poster={poster || undefined}
                controls
                playsInline
                preload="metadata"
                onLoadedMetadata={() => {
                    if (snapshot.current?.state === "playing") start();
                }}
                onSeeking={align}
                onPlay={() => {
                    align();
                    setNotice("");
                    setPlay(false);
                }}
                onPause={() => {
                    if (snapshot.current?.state === "playing") {
                        setNotice(
                            "Reanudar te llevará al momento actual de la emisión.",
                        );
                        setPlay(true);
                    }
                }}
                onError={() =>
                    setNotice(
                        "No se pudo cargar el video. Comprueba tu conexión y vuelve a abrir la sala.",
                    )
                }
            />
            <span className="react-player-label">
                {state === "ended"
                    ? "Pregrabado · Disponible bajo demanda"
                    : "Emisión programada · Pregrabado"}
            </span>
            {notice && (
                <div className="react-player-notice" role="status">
                    <span aria-hidden="true">▷</span>
                    <p>{notice}</p>
                    {play && (
                        <button className="studio-button" onClick={start}>
                            Reproducir
                        </button>
                    )}
                </div>
            )}
        </>
    );
}
export default function Player({ event: e, onScheduledState, recordingRef }) {
    const video = useRef(),
        [error, setError] = useState(""),
        [retry, setRetry] = useState(0);
    const live = e.status === "en_vivo" && !e.scheduled;
    useEffect(() => {
        if (!live || !e.hlsUrl) return;
        let hls,
            stopped = false;
        const v = video.current;
        setError("");
        async function attach() {
            try {
                if (v.canPlayType("application/vnd.apple.mpegurl")) {
                    v.src = e.hlsUrl;
                    return;
                }
                const { default: Hls } = await import("hls.js");
                if (stopped) return;
                if (!Hls.isSupported()) {
                    setError("Este navegador no admite la emisión.");
                    return;
                }
                hls = new Hls({
                    lowLatencyMode: true,
                    liveSyncDurationCount: 2,
                    liveMaxLatencyDurationCount: 4,
                    maxLiveSyncPlaybackRate: 1.15,
                    maxBufferLength: 5,
                    maxMaxBufferLength: 8,
                    backBufferLength: 0,
                });
                hls.loadSource(e.hlsUrl);
                hls.attachMedia(v);
                hls.on(Hls.Events.MANIFEST_PARSED, () =>
                    v.play().catch(() => {}),
                );
                hls.on(Hls.Events.ERROR, (_, d) => {
                    if (d.fatal)
                        setError(
                            "La señal no está disponible en este momento.",
                        );
                });
            } catch {
                if (!stopped) setError("No se pudo cargar el reproductor.");
            }
        }
        attach();
        return () => {
            stopped = true;
            hls?.destroy();
            v.removeAttribute("src");
            v.load();
        };
    }, [e.hlsUrl, live, retry]);
    if (e.scheduled)
        return (
            <ScheduledPlayer
                scheduled={e.scheduled}
                poster={e.image}
                onState={onScheduledState}
            />
        );
    if (live && e.hlsUrl)
        return (
            <>
                <video
                    ref={video}
                    className="uebtv-video"
                    controls
                    autoPlay
                    muted
                    playsInline
                    onPlaying={() => setError("")}
                />
                {error && (
                    <div className="react-player-notice" role="status">
                        <p>{error}</p>
                        <button
                            className="studio-button"
                            onClick={() => setRetry((x) => x + 1)}
                        >
                            Reintentar
                        </button>
                    </div>
                )}
            </>
        );
    if (e.videoUrl)
        return (
            <video
                ref={recordingRef}
                className="uebtv-video"
                src={e.videoUrl}
                controls
                playsInline
                preload="metadata"
                poster={e.image || undefined}
            />
        );
    return (
        <div className="uebtv-player-empty">
            <span className="uebtv-player-empty__icon">▶</span>
            <h2>
                {e.status === "finalizado"
                    ? "Transmisión finalizada"
                    : "Video próximamente"}
            </h2>
            <p>
                {e.status === "finalizado"
                    ? ["waiting", "processing", "recording"].includes(
                          e.recordingStatus,
                      )
                        ? "Estamos preparando la grabación. Estará disponible aquí automáticamente."
                        : "La grabación todavía no está disponible."
                    : "Esta sala se habilitará cuando comience la transmisión."}
            </p>
        </div>
    );
}
