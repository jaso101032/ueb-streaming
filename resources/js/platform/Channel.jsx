import React, { useEffect, useRef, useState } from "react";
import Player from "./Player";
import { useShell } from "./shared";
import UebtvMark from "./UebtvMark";

export function durationLabel(seconds = 0) {
    const s = Math.max(0, Math.floor(seconds));
    return `${Math.floor(s / 3600)} h ${Math.floor((s % 3600) / 60)} min ${s % 60} s`;
}

// The server owns the clock. Refreshes and late arrivals use the same position.
function ChannelVideo({ snapshot, received }) {
    const video = useRef(null);
    const latest = useRef({ snapshot, received });
    latest.current = { snapshot, received };
    const [notice, setNotice] = useState("");
    const align = () => {
        const { snapshot: s, received: t } = latest.current;
        const v = video.current;
        if (!v || v.readyState < 1) return;
        const target = Math.min(
            s.playback.duration,
            s.playback.position +
                (s.playback.state === "playing"
                    ? (performance.now() - t) / 1000
                    : 0),
        );
        if (Math.abs(v.currentTime - target) > 1.2) v.currentTime = target;
        if (v.playbackRate !== 1) v.playbackRate = 1;
    };
    const play = () => {
        align();
        video.current
            ?.play()
            .then(() => setNotice(""))
            .catch(() => setNotice("Pulsa reproducir para unirte a la señal."));
    };
    useEffect(() => {
        align();
        if (snapshot.playback.state === "paused") video.current?.pause();
        else play();
    }, [snapshot]);
    return (
        <>
            <video
                ref={video}
                className="uebtv-video"
                src={snapshot.event.scheduled.mediaUrl}
                controls
                autoPlay
                muted
                playsInline
                onLoadedMetadata={play}
                onSeeking={align}
                onRateChange={align}
                onError={() =>
                    setNotice(
                        "No se pudo cargar el archivo. Reintentando con la programación…",
                    )
                }
            />
            {notice && (
                <div className="react-player-notice" role="status">
                    <p>{notice}</p>
                    <button className="studio-button" onClick={play}>
                        Reproducir
                    </button>
                </div>
            )}
        </>
    );
}

export default function Channel({ stateUrl }) {
    const shell = useShell();
    const [data, setData] = useState(null);
    const [received, setReceived] = useState(0);
    const [error, setError] = useState("");
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
    const playing = live || data?.mode === "scheduled";
    const next = data?.next?.[0];
    return (
        <div className="studio-page channel-page">
            <header className="channel-heading">
                <div>
                    <p className="studio-eyebrow">
                        EL CANAL DE NUESTRA COMUNIDAD
                    </p>
                    <h1>
                        Tu universidad.
                        <br />
                        <em>Siempre cerca.</em>
                    </h1>
                    <p>Conocimiento, cultura e historias que nos conectan.</p>
                </div>
                <a className="channel-catalog" href={shell.catalog}>
                    Explorar el catálogo <span aria-hidden="true">↗</span>
                </a>
            </header>
            {error && (
                <p role="status" className="studio-alert">
                    {error}
                </p>
            )}
            <div className="channel-layout">
                <section className="channel-main" aria-label="Señal de UEBTV">
                    <div className="channel-screen-frame">
                        <div className="channel-screen-bar">
                            <span>
                                <i className={live ? "is-live" : ""} /> UEBTV{" "}
                                <small>SEÑAL CONTINUA</small>
                            </span>
                            <span className="channel-screen-status">
                                {live
                                    ? "EN DIRECTO"
                                    : playing
                                      ? "PREGRABADO"
                                      : data
                                        ? "ENTRE EMISIONES"
                                        : "CONECTANDO"}
                            </span>
                        </div>
                        <div
                            className={`channel-screen ${playing ? "is-playing" : "is-idle"}`}
                        >
                            {live ? (
                                <Player
                                    key={`live-${data.event.id}`}
                                    event={data.event}
                                />
                            ) : data?.mode === "scheduled" ? (
                                <ChannelVideo
                                    key={`video-${data.event.id}`}
                                    snapshot={data}
                                    received={received}
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
                                        <UebtvMark className="uebtv-brand-logo--channel" />
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
                        <div className="channel-now-top">
                            <span
                                className={`channel-badge ${live ? "channel-live" : ""}`}
                            >
                                {live
                                    ? "● En directo"
                                    : playing
                                      ? "Pregrabado · Emisión sincronizada"
                                      : "Nuestra señal"}
                            </span>
                            <span className="channel-auto">
                                ↻ Programación automática
                            </span>
                        </div>
                        <h2>
                            {data?.event?.title ||
                                "Un espacio para encontrarnos"}
                        </h2>
                        <p>
                            {live
                                ? `La programación está en pausa.${data.resumeTitle ? ` Después continuamos con «${data.resumeTitle}».` : " Continuará al terminar el directo."}`
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
    );
}
