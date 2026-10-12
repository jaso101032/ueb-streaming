import React, { useEffect, useState } from "react";
import {
    Action,
    Feedback,
    Heading,
    Pagination,
    statusLabel,
    useShell,
} from "./shared";
export function EventList({ events, pagination }) {
    return (
        <div className="studio-page">
            <Heading eyebrow="GESTIÓN DE CONTENIDO" title="Gestionar eventos">
                Aquí creas, editas y consultas todos los eventos y sus grabaciones.
            </Heading>
            <Feedback />
            <section className="studio-panel studio-management">
                <div className="studio-panel-heading">
                    <h2>Eventos y grabaciones</h2>
                    <span>{pagination.total} eventos</span>
                </div>
                {events.map((e) => (
                    <article className="studio-management-row" key={e.id}>
                        <div className="studio-event-thumb">
                            {e.image ? <img src={e.image} alt="" /> : "▶"}
                        </div>
                        <div className="studio-event-info">
                            <small>{e.category}</small>
                            <h3>
                                <a href={e.detailUrl}>{e.title}</a>
                            </h3>
                            <p>
                                {e.date} · {e.faculty}
                            </p>
                        </div>
                        <span
                            className={`studio-status studio-status-${e.status}`}
                        >
                            {statusLabel(e)}
                        </span>
                        <div className="studio-row-actions">
                            <a href={e.detailUrl}>Ver</a>
                            {e.editUrl && <a href={e.editUrl}>Editar</a>}
                            {e.deleteUrl && (
                                <Action
                                    url={e.deleteUrl}
                                    method="DELETE"
                                    className="studio-delete"
                                    confirm="¿Eliminar este evento del catálogo?"
                                >
                                    Eliminar
                                </Action>
                            )}
                        </div>
                    </article>
                ))}
                {!events.length && (
                    <div className="studio-empty">
                        <h3>Tu próxima historia empieza aquí</h3>
                        <p>Crea un evento desde el panel lateral.</p>
                    </div>
                )}
                <Pagination pagination={pagination} />
            </section>
        </div>
    );
}
function Recording({ event: e }) {
    return (
        <div className="react-recording">
            <span className="studio-eyebrow">GRABACIÓN</span>
            <h3>
                {{
                    ready: "Grabación disponible",
                    processing: "Preparando el video",
                    waiting: "Esperando el archivo",
                    recording: "Grabando la emisión",
                    failed: "No se pudo preparar la grabación",
                }[e.recordingStatus] || "Sin grabación automática"}
            </h3>
            {e.videoUrl && (
                <a className="studio-text-link" href={e.url}>
                    Ver video ↗
                </a>
            )}
            {e.downloadUrl && (
                <a className="studio-text-link" href={e.downloadUrl}>
                    Descargar MP4 ↧
                </a>
            )}
            {e.retryUrl && (
                <Action url={e.retryUrl}>Reintentar grabación</Action>
            )}
        </div>
    );
}
export function EventDetail({ event: e }) {
    const shell = useShell();
    return (
        <div className="studio-page">
            <Heading eyebrow={e.category} title={e.title}>
                Detalle del evento · {statusLabel(e)} · {e.date}
            </Heading>
            <Feedback />
            <div className="react-toolbar">
                <a className="studio-button" href={e.url}>
                    Ver sala pública ↗
                </a>
                {e.editUrl && (
                    <a
                        className="studio-button studio-button--light"
                        href={e.editUrl}
                    >
                        Editar evento
                    </a>
                )}
                {e.finishUrl && (
                    <Action
                        url={e.finishUrl}
                        confirm="¿Finalizar la transmisión y preparar su grabación?"
                    >
                        Finalizar transmisión
                    </Action>
                )}
            </div>
            <div className="react-detail-grid">
                <article className="studio-panel react-panel">
                    {e.image && (
                        <img
                            className="react-cover"
                            src={e.image}
                            alt={e.title}
                        />
                    )}
                    <h2>Acerca del evento</h2>
                    <p className="react-description">
                        {e.description || "Sin descripción."}
                    </p>
                    <dl className="react-facts">
                        {Object.entries({
                            Estado: statusLabel(e),
                            Tipo: e.type,
                            Organizador: e.organizer,
                            Facultad: e.faculty,
                            Carrera: e.career,
                            Inicio: e.date,
                            Lugar: e.place || "Sin definir",
                        }).map(([k, v]) => (
                            <div key={k}>
                                <dt>{k}</dt>
                                <dd>{v}</dd>
                            </div>
                        ))}
                    </dl>
                </article>
                <aside className="studio-panel react-panel">
                    <Recording event={e} />
                    <p>
                        Si este evento se transmite en vivo, su señal y
                        credenciales se gestionan desde “Emitir con OBS”.
                    </p>
                    <a
                        className="studio-text-link"
                        href={
                            shell.links.find((l) => l.label === "Emitir con OBS")
                                ?.url
                        }
                    >
                        Ir a Emitir con OBS ↗
                    </a>
                </aside>
            </div>
        </div>
    );
}
function StreamCard({ event: e }) {
    const [signal, setSignal] = useState("Comprobando señal…"),
        [copy, setCopy] = useState("");
    useEffect(() => {
        let stopped = false,
            timer,
            controller;
        async function refresh() {
            if (!document.hidden) {
                controller = new AbortController();
                const timeout = setTimeout(() => controller.abort(), 8000);
                try {
                    const r = await fetch(e.statusUrl, {
                        signal: controller.signal,
                        headers: { Accept: "application/json" },
                    });
                    if (!r.ok) throw Error();
                    const d = await r.json();
                    if (!stopped)
                        setSignal(
                            d.finalizado
                                ? "Transmisión finalizada"
                                : d.en_vivo
                                  ? "Señal recibida"
                                  : "○ Esperando señal de OBS",
                        );
                } catch {
                    if (!stopped) setSignal("No se pudo consultar la señal");
                } finally {
                    clearTimeout(timeout);
                }
            }
            if (!stopped) timer = setTimeout(refresh, 10000);
        }
        refresh();
        return () => {
            stopped = true;
            clearTimeout(timer);
            controller?.abort();
        };
    }, [e.statusUrl]);
    async function copyValue(v) {
        try {
            await navigator.clipboard.writeText(v);
            setCopy("Copiado");
        } catch {
            setCopy("Selecciona el texto y cópialo manualmente.");
        }
    }
    return (
        <article className="studio-panel react-stream-card">
            <div>
                <span className="studio-eyebrow">{e.category}</span>
                <h2>{e.title}</h2>
                <p
                    role="status"
                    className={`react-stream-signal ${
                        signal === "Señal recibida"
                            ? "is-live"
                            : signal === "Transmisión finalizada"
                              ? "is-finished"
                              : signal.startsWith("○")
                                ? "is-waiting"
                                : "is-error"
                    }`}
                >
                    {signal.replace(/^[○●]\s*/, "")}
                </p>
            </div>
            {e.streamKey ? (
                <div className="react-credentials">
                    {[
                        ["Servidor", e.server],
                        ["Clave", e.streamKey],
                    ].map(([k, v]) => (
                        <div key={k}>
                            <small>{k}</small>
                            <code>{v}</code>
                            <button
                                type="button"
                                onClick={() => copyValue(v)}
                                aria-label={`Copiar ${k}`}
                            >
                                Copiar
                            </button>
                        </div>
                    ))}
                    <small role="status">{copy}</small>
                </div>
            ) : (
                <p>Genera una clave para conectar OBS.</p>
            )}
            <div className="react-toolbar">
                {e.generateUrl && (
                    <Action
                        url={e.generateUrl}
                        confirm={
                            e.streamKey
                                ? "¿Reemplazar la clave actual de OBS?"
                                : undefined
                        }
                    >
                        {e.streamKey ? "Regenerar clave" : "Generar clave OBS"}
                    </Action>
                )}
                <a href={e.url} className="studio-text-link">
                    Ver sala ↗
                </a>
                <a href={e.detailUrl} className="studio-text-link">
                    Evento y grabación
                </a>
                {e.finishUrl && (
                    <Action
                        url={e.finishUrl}
                        confirm="¿Finalizar esta transmisión?"
                        className="react-danger"
                    >
                        Finalizar transmisión
                    </Action>
                )}
            </div>
        </article>
    );
}
export function Streaming({ events, pagination }) {
    return (
        <div className="studio-page">
            <Heading eyebrow="OPERACIÓN EN VIVO" title="Emitir con OBS">
                Solo aparecen eventos que requieren señal de OBS. Las
                grabaciones se consultan desde Gestionar eventos y los videos
                programados desde Parrilla 24/7.
            </Heading>
            <Feedback />
            <div className="react-stack">
                {events.map((e) =>
                    e.scheduled ? (
                        <article
                            className="studio-panel react-panel"
                            key={e.id}
                        >
                            <h2>{e.title}</h2>
                            <p>Emisión pregrabada · {statusLabel(e)}</p>
                            <a href={e.url}>Ver sala ↗</a>
                        </article>
                    ) : (
                        <StreamCard event={e} key={e.id} />
                    ),
                )}
                <Pagination pagination={pagination} />
                {!events.length && (
                    <div className="studio-panel react-panel">
                        No hay emisiones de OBS pendientes. Crea o revisa un
                        evento desde Gestionar eventos.
                    </div>
                )}
            </div>
        </div>
    );
}
