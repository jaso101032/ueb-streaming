import React, { useEffect, useRef, useState } from "react";
import { useShell, statusLabel } from "./shared";
import Player from "./Player";
import Chat from "./Chat";
import UebtvMark from "./UebtvMark";
export default function Watch({
    event: e,
    initialReady,
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
        [feedback, setFeedback] = useState(""),
        [scheduledState, setScheduledState] = useState(e.scheduled?.state);
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
                            <UebtvMark className="uebtv-brand-logo--watch-cover" />
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
                        <div className="uebtv-watch-player" ref={player}>
                            <div className="uebtv-watch-player__topline">
                                <span
                                    className={`uebtv-watch-live-dot ${!e.scheduled && e.status === "en_vivo" ? "is-live" : "is-recorded"}`}
                                />
                                <span>
                                    {e.scheduled
                                        ? "Sala de emisión programada"
                                        : e.status === "en_vivo"
                                          ? "Transmisión en directo"
                                          : "Sala UEBTV"}
                                </span>
                                <span className="uebtv-watch-quality">
                                    UEBTV
                                </span>
                            </div>
                            <Player
                                event={e}
                                onScheduledState={setScheduledState}
                                recordingRef={recordingVideo}
                            />
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
                            <div className="uebtv-watch-meta">
                                <span>{e.faculty}</span>
                                <span>·</span>
                                <span>Organiza {e.organizer}</span>
                                <span>·</span>
                                <span>{e.date}</span>
                            </div>
                            <p className="uebtv-watch-description">
                                {e.description ||
                                    "Una transmisión de la Universidad Estatal de Bolívar para toda nuestra comunidad."}
                            </p>
                            <div className="uebtv-watch-actions">
                                <button onClick={() => share(true)}>
                                    ↗ Compartir
                                </button>
                                <button onClick={() => share()}>
                                    ▣ Copiar enlace
                                </button>
                                <a href={shell.channel}>Señal continua ↗</a>
                                <a href={shell.catalog}>← Catálogo</a>
                                <span role="status">{feedback}</span>
                            </div>
                        </div>
                    </section>
                    <Chat
                        endpoint={e.chatUrl}
                        initial={messages}
                        canModerate={canModerate}
                        seekable={e.status === "finalizado" && Boolean(e.videoUrl)}
                        onSeek={(seconds) => {
                            const video = recordingVideo.current;
                            if (!video) return;
                            video.currentTime = seconds;
                            video.play().catch(() => {});
                        }}
                        isRecording={
                            e.status === "finalizado" ||
                            scheduledState === "ended"
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
