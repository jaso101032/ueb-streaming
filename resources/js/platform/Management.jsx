import React, { useEffect, useState } from "react";
import {
    Action,
    Feedback,
    Heading,
    Pagination,
    Token,
    statusLabel,
    useShell,
} from "./shared";
const formatBytes = (bytes) => {
    if (!Number.isFinite(bytes) || bytes < 0) return "—";
    const index = Math.min(4, Math.floor(Math.log(Math.max(bytes, 1)) / Math.log(1024)));
    return `${new Intl.NumberFormat("es-EC", { maximumFractionDigits: 2 }).format(bytes / 1024 ** index)} ${["B", "KiB", "MiB", "GiB", "TiB"][index]}`;
};
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
                            <a className="studio-icon-action" href={e.detailUrl} aria-label={`Ver evento ${e.title}`} title={`Ver ${e.title}`}><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2.5 12s3.5-5 9.5-5 9.5 5 9.5 5-3.5 5-9.5 5-9.5-5-9.5-5Z" /><circle cx="12" cy="12" r="2.5" /></svg><span className="studio-action-tooltip" role="tooltip">Ver</span></a>
                            {e.editUrl ? <a className="studio-icon-action" href={e.editUrl} aria-label={`Editar evento ${e.title}`} title={`Editar ${e.title}`}><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m14 5 5 5M4 20l4.2-.8L19 8.4a2.1 2.1 0 0 0-3-3L5.2 16.2 4 20Z" /></svg><span className="studio-action-tooltip" role="tooltip">Editar</span></a>
                                : <button type="button" className="studio-icon-action" disabled aria-label={`No se puede editar ${e.title}`} title="Este evento no se puede editar"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m14 5 5 5M4 20l4.2-.8L19 8.4a2.1 2.1 0 0 0-3-3L5.2 16.2 4 20Z" /></svg><span className="studio-action-tooltip" role="tooltip">No disponible</span></button>}
                            {e.deleteUrl && (
                                <Action
                                    url={e.deleteUrl}
                                    method="DELETE"
                                    className="studio-delete studio-icon-action"
                                    confirm="¿Eliminar este evento del catálogo?"
                                    ariaLabel={`Eliminar evento ${e.title}`}
                                >
                                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M10 11v6m4-6v6M6 7l1 13h10l1-13M9 7V4h6v3" /></svg><span className="studio-action-tooltip" role="tooltip">Eliminar</span>
                                </Action>
                            ) || <button type="button" className="studio-delete studio-icon-action" disabled aria-label={`No se puede eliminar ${e.title}`} title="Este evento no se puede eliminar"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M10 11v6m4-6v6M6 7l1 13h10l1-13M9 7V4h6v3" /></svg><span className="studio-action-tooltip" role="tooltip">No disponible</span></button>}
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
    const metadata = e.recordingMetadata;
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
            {metadata && (
                <dl className="react-facts" aria-label="Datos técnicos de la grabación">
                    {Object.entries({
                        ...(metadata.mp4Bytes != null ? { "Archivo publicado": `MP4 · ${formatBytes(metadata.mp4Bytes)}` } : {}),
                        ...(metadata.rawBytes != null ? { [metadata.rawPresent ? "FLV original almacenado" : "Origen temporal procesado"]: `FLV · ${formatBytes(metadata.rawBytes)}` } : {}),
                        ...(metadata.rawCleanupPending ? { "Limpieza pendiente": "El MP4 ya está disponible; se reintentará eliminar el FLV temporal." } : {}),
                        ...(metadata.rawSha256 ? { "Huella SHA-256 del original": metadata.rawSha256 } : {}),
                        ...(metadata.mp4Sha256 ? { "Huella SHA-256 del MP4": metadata.mp4Sha256 } : {}),
                        ...(metadata.videoCodec ? { "Video": `${metadata.videoCodec.toUpperCase()} · ${metadata.width || "?"} × ${metadata.height || "?"} · ${metadata.fps ?? "?"} FPS` } : {}),
                        ...(metadata.audioCodec ? { "Audio": metadata.audioCodec.toUpperCase() } : {}),
                        ...(metadata.durationSeconds != null ? { "Duración": `${Math.round(metadata.durationSeconds)} s` } : {}),
                    }).map(([label, value]) => <div key={label}><dt>{label}</dt><dd>{value}</dd></div>)}
                </dl>
            )}
            {e.publication && e.recordingStatus === "ready" && (
                <div className="react-publication" role="status">
                    <strong>
                        {{
                            pendiente: "Pendiente de aprobación: el público todavía no puede verla.",
                            aprobada: "Publicada para su audiencia.",
                            retirada: "Retirada: el público ya no puede verla.",
                        }[e.publication.status] || e.publication.status}
                    </strong>
                    {e.publication.reviewedBy && (
                        <small>Revisada por {e.publication.reviewedBy} · {e.publication.reviewedAt}</small>
                    )}
                    {e.publication.approveUrl && (
                        <Action url={e.publication.approveUrl}>Aprobar y publicar</Action>
                    )}
                    {e.publication.withdrawUrl && (
                        <Action url={e.publication.withdrawUrl} className="studio-button studio-button-soft" confirm="¿Retirar esta grabación? Dejará de estar disponible de inmediato, incluidos los enlaces directos.">Retirar grabación</Action>
                    )}
                </div>
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
                {e.chatToggleUrl && <form method="POST" action={e.chatToggleUrl}>
                    <input type="hidden" name="_token" value={shell.csrf} />
                    <input type="hidden" name="enabled" value={e.chatEnabled ? "0" : "1"} />
                    <button className="studio-button studio-button--light" type="submit">{e.chatEnabled ? "Desactivar comentarios" : "Activar comentarios"}</button>
                </form>}
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
                    {shell.links.find((l) => l.label === "Emitir con OBS") && <a className="studio-text-link" href={shell.links.find((l) => l.label === "Emitir con OBS").url}>Ir a Emitir con OBS ↗</a>}
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
                {e.detailUrl && <a href={e.detailUrl} className="studio-text-link">Evento y grabación</a>}
                {e.finishUrl && (
                    <Action
                        url={e.finishUrl}
                        confirm="¿Finalizar esta transmisión?"
                        className="react-danger"
                    >
                        Finalizar transmisión
                    </Action>
                )}
                {e.chatToggleUrl && <form method="POST" action={e.chatToggleUrl}>
                    <Token />
                    <input type="hidden" name="enabled" value={e.chatEnabled ? "0" : "1"} />
                    <button className="studio-button studio-button--light" type="submit">{e.chatEnabled ? "Desactivar comentarios" : "Activar comentarios"}</button>
                </form>}
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
