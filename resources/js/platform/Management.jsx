import React, { useEffect, useState } from "react";
import { ProgramDrawer } from "../components/ScheduledStudio";
import {
    Action,
    Feedback,
    PageHeader,
    copyText,
    Pagination,
    SubtitleForm,
    Token,
    statusLabel,
    useShell,
} from "./shared";
export function EventList({ events, pagination, filters = {}, counts = {}, baseUrl = "/admin/eventos" }) {
    return (
        <div className="studio-page pg">
            <PageHeader eyebrow="Transmitir" title="Eventos" description="Directos, programas y grabaciones: crea, edita y revisa cada evento." />
            <Feedback />
            <div className="ev-filter">
                <nav className="ev-tabs" aria-label="Filtrar por estado">
                    {[["", "Todos", counts.todos], ["en_vivo", "En vivo", counts.en_vivo], ["programado", "Programados", counts.programado], ["finalizado", "Finalizados", counts.finalizado]].map(([key, label, n]) => {
                        const params = new URLSearchParams(); if (filters.q) params.set("q", filters.q); if (key) params.set("estado", key);
                        const active = (filters.estado || "") === key;
                        return <a key={key || "todos"} href={`${baseUrl}${params.toString() ? `?${params}` : ""}`} className={active ? "is-active" : ""} aria-current={active ? "page" : undefined}>{key === "en_vivo" && <i aria-hidden="true" />}{label} <b>{n || 0}</b></a>;
                    })}
                </nav>
                <form method="GET" action={baseUrl} className="ev-search" role="search">
                    {filters.estado && <input type="hidden" name="estado" value={filters.estado} />}
                    <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="6.5" /><path d="m20 20-4.2-4.2" /></svg>
                    <input type="search" name="q" defaultValue={filters.q || ""} maxLength="120" placeholder="Buscar por título" aria-label="Buscar eventos por título" />
                    {filters.q && <a href={`${baseUrl}${filters.estado ? `?estado=${filters.estado}` : ""}`} aria-label="Borrar búsqueda" className="ev-search__clear">✕</a>}
                </form>
            </div>
            <section className="studio-panel studio-management">
                <div className="studio-panel-heading">
                    <h2>{filters.q ? <>Resultados para «{filters.q}»</> : "Eventos"}</h2>
                    <span>{pagination.total} {pagination.total === 1 ? "evento" : "eventos"}</span>
                </div>
                {events.map((e) => (
                    <article className="studio-management-row" key={e.id}>
                        <div className={`studio-event-thumb ${e.image ? "has-image" : ""}`}>
                            {e.image ? <img src={e.image} alt="" /> : <img className="studio-event-thumb__logo" src="/images/brand/ueb-wordmark.png" alt="" />}
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
                            {e.downloadUrl
                                ? <a className="studio-icon-action" href={e.downloadUrl} aria-label={`Descargar video de ${e.title}`}><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 4v11m-5-5 5 5 5-5M5 20h14" /></svg><span className="studio-action-tooltip" role="tooltip">Descargar video</span></a>
                                : <button type="button" className="studio-icon-action" disabled aria-label={`${e.title}: aún no tiene video para descargar`}><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 4v11m-5-5 5 5 5-5M5 20h14" /></svg><span className="studio-action-tooltip" role="tooltip">Sin video para descargar</span></button>}
                            {e.deleteUrl && (
                                <Action
                                    url={e.deleteUrl}
                                    method="DELETE"
                                    className="studio-delete studio-icon-action"
                                    confirm="¿Eliminar este evento? Se borra con su video y todos sus archivos. No se puede deshacer: si quieres conservar el video, descárgalo antes."
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
                        <h3>{filters.q || filters.estado ? "No hay eventos con estos filtros" : "Aún no hay eventos"}</h3>
                        <p>{filters.q || filters.estado ? <a href={baseUrl}>Ver todos los eventos</a> : "Usa «Crear evento» en el menú lateral para empezar."}</p>
                    </div>
                )}
                <Pagination pagination={pagination} />
            </section>
        </div>
    );
}
/* ───────────── Detalle del evento ─────────────
 * Arriba el video (lo que más importa), al lado lo que el administrador
 * decide: publicación, archivo, calidades y subtítulos. Los datos técnicos
 * (códecs y huellas SHA-256) quedan plegados en «Ver detalles técnicos». */
const sizeLabel = (bytes) => {
    if (!Number.isFinite(bytes) || bytes <= 0) return "—";
    if (bytes >= 1024 ** 3) return `${(bytes / 1024 ** 3).toLocaleString("es-EC", { maximumFractionDigits: 1 })} GB`;
    if (bytes >= 1024 ** 2) return `${(bytes / 1024 ** 2).toLocaleString("es-EC", { maximumFractionDigits: 1 })} MB`;
    return `${Math.max(1, Math.round(bytes / 1024))} KB`;
};
const durationLabel = (seconds) => {
    if (!Number.isFinite(seconds) || seconds <= 0) return null;
    const total = Math.round(seconds), h = Math.floor(total / 3600), m = Math.floor((total % 3600) / 60), sec = total % 60;
    return h ? `${h} h ${String(m).padStart(2, "0")} min` : m ? `${m} min ${String(sec).padStart(2, "0")} s` : `${sec} s`;
};
const RECORDING_STATE = {
    ready: ["Grabación disponible", "ok"],
    processing: ["Preparando el video…", "work"],
    waiting: ["Esperando el archivo de la grabación", "work"],
    recording: ["Grabando la emisión", "live"],
    failed: ["No se pudo preparar la grabación", "bad"],
};
const PUBLICATION = {
    pendiente: ["Pendiente de aprobación", "warn", "El público todavía no puede verla. Para publicarla se necesita una autorización de difusión vigente."],
    aprobada: ["Publicada", "ok", "La comunidad puede verla en el catálogo."],
    retirada: ["Retirada", "bad", "El público ya no puede verla, ni con enlace directo."],
};

function VdIcon({ name }) {
    const p = {
        play: <path d="m9 7 8 5-8 5z" />,
        download: <path d="M12 4v11m-5-5 5 5 5-5M5 20h14" />,
        chart: <path d="M4 20V10m6 10V4m6 16v-7m4 7H3" />,
        clock: <><circle cx="12" cy="12" r="8.5" /><path d="M12 7.5V12l3 2" /></>,
        film: <><rect x="3.5" y="5" width="17" height="14" rx="2" /><path d="M8 5v14m8-14v14M3.5 9.5H8m8 0h4.5m-17 5H8m8 0h4.5" /></>,
        file: <path d="M14 3.5H7a1.5 1.5 0 0 0-1.5 1.5v14A1.5 1.5 0 0 0 7 20.5h10a1.5 1.5 0 0 0 1.5-1.5V8zM14 3.5V8h4.5" />,
        chevron: <path d="m9 6 6 6-6 6" />,
        copy: <><rect x="8.5" y="8.5" width="11" height="11" rx="2" /><path d="M15.5 8.5V6a1.5 1.5 0 0 0-1.5-1.5H6A1.5 1.5 0 0 0 4.5 6v8A1.5 1.5 0 0 0 6 15.5h2.5" /></>,
        check: <path d="m5 12 4.5 4.5L19 7" />,
    };
    return <svg className="vd-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">{p[name]}</svg>;
}

function Hash({ label, value }) {
    const [copied, setCopied] = useState(false);
    return <div className="vd-hash">
        <dt>{label}</dt>
        <dd>
            <code title={value}>{value.slice(0, 12)}…{value.slice(-8)}</code>
            <button type="button" onClick={async () => { if (await copyText(value)) { setCopied(true); setTimeout(() => setCopied(false), 1800); } }} aria-label={`Copiar ${label.toLowerCase()}`}>
                <VdIcon name={copied ? "check" : "copy"} />{copied ? "Copiada" : "Copiar"}
            </button>
        </dd>
    </div>;
}

/** Video arriba: vista previa o el estado en que se encuentra. */
function MediaPreview({ event: e }) {
    const metadata = e.recordingMetadata || {};
    const file = e.scheduledFile;
    const [stateText, tone] = file
        ? (file?.status === "ready" ? ["Video listo", "ok"] : file?.status === "failed" ? ["No se pudo preparar el video", "bad"] : ["Preparando el video…", "work"])
        : RECORDING_STATE[e.recordingStatus] || [e.status === "programado" ? "La grabación empezará cuando inicie la transmisión" : "Sin grabación", "muted"];
    const duration = durationLabel(file ? file.duration : metadata.durationSeconds);
    const quality = metadata.height ? `${metadata.height}p${metadata.fps ? ` · ${Math.round(metadata.fps)} FPS` : ""}` : null;
    return (
        <section className="vd-card vd-media" aria-label="Video del evento">
            {e.previewUrl
                ? <video className="vd-media__video" src={e.previewUrl} poster={e.image || undefined} controls preload="metadata" playsInline />
                : <div className={`vd-media__empty ${e.image ? "has-image" : ""}`} style={e.image ? { backgroundImage: `url(${JSON.stringify(e.image)})` } : undefined}>
                    <span className={`vd-state is-${tone}`}>{tone === "live" && <i aria-hidden="true" />}{stateText}</span>
                    {tone === "work" && <small>Actualiza la página en unos minutos.</small>}
                </div>}
            {(e.previewUrl || duration || quality || e.downloadUrl || e.videoUrl) && <div className="vd-media__bar">
                <ul className="vd-chips" aria-label="Resumen del video">
                    {e.previewUrl && <li className={`vd-state is-${tone}`}>{stateText}</li>}
                    {duration && <li><VdIcon name="clock" />{duration}</li>}
                    {quality && <li><VdIcon name="film" />{quality}</li>}
                </ul>
                <div className="vd-media__actions">
                    {e.analyticsUrl && e.previewUrl && <a className="pg-btn" href={e.analyticsUrl}><VdIcon name="chart" />Estadísticas</a>}
                    {e.downloadUrl && <a className="pg-btn" href={e.downloadUrl}><VdIcon name="download" />Descargar MP4</a>}
                    {!e.previewUrl && e.videoUrl && <a className="pg-btn" href={e.url} target="_blank" rel="noopener"><VdIcon name="play" />Ver video</a>}
                </div>
            </div>}
        </section>
    );
}

/** Quién puede ver la grabación y los botones para cambiarlo. */

const AUTHORIZATION = {
    vigente: ["Autorización vigente", "ok", "El evento se muestra según su visibilidad."],
    futura: ["Autorización con fecha futura", "warn", "Solo el personal lo ve hasta esa fecha."],
    vencida: ["Autorización vencida", "bad", "Solo el personal lo ve. Registra una autorización vigente."],
    sin_registrar: ["Sin autorización de difusión", "bad", "Solo el personal lo ve: ni el directo ni el video se muestran al público."],
};

/** Autorización de difusión: sin ella, nada del evento se muestra al público. */
function AuthorizationCard({ event: e }) {
    const shell = useShell();
    const a = e.broadcastAuthorization;
    if (!a) return null;
    const [label, tone, help] = AUTHORIZATION[a.status] || AUTHORIZATION.sin_registrar;
    return (
        <section className="vd-card vd-side-card vd-auth" aria-labelledby="vd-auth-title">
            <h2 id="vd-auth-title" className="vd-eyebrow">Autorización de difusión</h2>
            <p className={`vd-pub is-${tone}`} role="status"><i aria-hidden="true" /><span><strong>{label}</strong><small>{help}</small></span></p>
            <form method="POST" action={a.updateUrl} className="vd-auth__form">
                <input type="hidden" name="_token" value={shell.csrf} />
                <input type="hidden" name="_method" value="PUT" />
                <label>Responsable que autoriza<input name="autorizacion_responsable" required maxLength="255" defaultValue={a.responsable || ""} placeholder="Nombre y cargo" /></label>
                <label>Documento de autorización<input name="autorizacion_referencia" required maxLength="255" defaultValue={a.referencia || ""} placeholder="Oficio, resolución o acta" /></label>
                <label>Alcance<input name="autorizacion_alcance" maxLength="500" defaultValue={a.alcance || ""} placeholder="Directo, grabación, imagen y voz de participantes…" /></label>
                <div className="vd-auth__dates">
                    <label>Fecha<input type="date" name="autorizacion_fecha" required defaultValue={a.fecha || ""} /></label>
                    <label>Vigente hasta<input type="date" name="autorizacion_vigencia" defaultValue={a.vigencia || ""} /></label>
                </div>
                <p className="vd-help">La licencia de uso no reemplaza esta autorización: la licencia indica cómo se puede reutilizar el video; la autorización, que se puede difundir.</p>
                <div className="vd-row-actions">
                    <button className="pg-btn is-primary" type="submit">Guardar autorización</button>
                    {a.revokeUrl && <Action url={a.revokeUrl} method="DELETE" className="pg-btn is-danger" confirm="¿Revocar la autorización? El evento dejará de mostrarse al público de inmediato.">Revocar</Action>}
                </div>
            </form>
        </section>
    );
}

function PublicationCard({ event: e }) {
    if (!e.publication || e.recordingStatus !== "ready") return null;
    const [label, tone, help] = PUBLICATION[e.publication.status] || [e.publication.status, "muted", ""];
    return (
        <section className="vd-card vd-side-card" aria-labelledby="vd-pub-title">
            <h2 id="vd-pub-title" className="vd-eyebrow">Publicación</h2>
            <p className={`vd-pub is-${tone}`} role="status"><i aria-hidden="true" /><span><strong>{label}</strong><small>{help}</small></span></p>
            {e.publication.reviewedBy && <p className="vd-muted">Revisada por {e.publication.reviewedBy} · {e.publication.reviewedAt}</p>}
            {(e.publication.approveUrl || e.publication.withdrawUrl) && <div className="vd-row-actions">
                {e.publication.approveUrl && <Action url={e.publication.approveUrl} className="pg-btn is-primary">Aprobar y publicar</Action>}
                {e.publication.withdrawUrl && <Action url={e.publication.withdrawUrl} className="pg-btn is-danger" confirm="¿Retirar esta grabación? Dejará de estar disponible de inmediato, incluidos los enlaces directos.">Retirar grabación</Action>}
            </div>}
        </section>
    );
}

/** El archivo: cuánto pesa, si se conserva el original, calidades y detalles técnicos. */
function FileCard({ event: e }) {
    const m = e.recordingMetadata;
    const file = e.scheduledFile;
    const r = e.renditions;
    if (!m && !file && !r && !e.retryUrl) return null;
    const technical = m && (m.videoCodec || m.audioCodec || m.rawSha256 || m.mp4Sha256);
    return (
        <section className="vd-card vd-side-card" aria-labelledby="vd-file-title">
            <h2 id="vd-file-title" className="vd-eyebrow">Archivo de video</h2>
            {e.recordingStatus === "failed" && <p className="vd-alert">No se pudo convertir la grabación. Revisa el espacio disponible e inténtalo de nuevo.</p>}
            {file?.status === "failed" && <p className="vd-alert">{file.error || "No se pudo preparar el video."}</p>}
            <dl className="vd-list">
                {file && ["pending", "processing"].includes(file.status) && <div><dt>Estado</dt><dd className="vd-muted">{file.status === "processing" ? `Convirtiendo… ${file.progress || 0}%` : "En cola para convertir"}</dd></div>}
                {m?.mp4Bytes != null && <div><dt>Video publicado</dt><dd>MP4 · {sizeLabel(m.mp4Bytes)}</dd></div>}
                {file?.bytes > 0 && <div><dt>Video publicado</dt><dd>MP4 · {sizeLabel(file.bytes)}</dd></div>}
                {m?.rawBytes != null && <div><dt>Original de OBS</dt><dd>{m.rawPresent ? <>Guardado · {sizeLabel(m.rawBytes)}</> : <span className="vd-muted">No se conserva</span>}</dd></div>}
                {m?.rawCleanupPending && <div><dt>Limpieza</dt><dd className="vd-muted">Se eliminará el archivo temporal de OBS.</dd></div>}
                {r && <div><dt>Calidades</dt><dd>
                    {r.status === "ready" ? <span className="vd-levels">{r.levels.map((name) => <b key={name}>{name}</b>)}</span>
                        : r.status === "failed" ? <span className="vd-bad">No se pudieron preparar</span>
                        : <span className="vd-muted">{r.status === "processing" ? `Preparando… ${r.progress || 0}%` : "En cola"}</span>}
                </dd></div>}
            </dl>
            {r?.status === "ready" && <p className="vd-help">El reproductor elige la calidad según la conexión de cada persona.</p>}
            {r && r.status !== "ready" && <p className="vd-help">{r.status === "failed" ? r.error || "Mientras tanto se reproduce el video original." : "Mientras tanto se reproduce el video original."}</p>}
            {r?.status === "processing" && <progress className="vd-progress" max="100" value={r.progress || 0} aria-label="Avance de las calidades" />}
            {e.retryUrl && <Action url={e.retryUrl} className="pg-btn is-primary">Reintentar grabación</Action>}
            {technical && <details className="vd-tech">
                <summary><VdIcon name="chevron" />Ver detalles técnicos</summary>
                <dl className="vd-list">
                    {m.videoCodec && <div><dt>Video</dt><dd>{m.videoCodec.toUpperCase()} · {m.width || "?"} × {m.height || "?"}{m.fps ? ` · ${m.fps} FPS` : ""}</dd></div>}
                    {m.audioCodec && <div><dt>Audio</dt><dd>{m.audioCodec.toUpperCase()}</dd></div>}
                    {m.durationSeconds != null && <div><dt>Duración exacta</dt><dd>{Math.round(m.durationSeconds)} s</dd></div>}
                </dl>
                {(m.rawSha256 || m.mp4Sha256) && <>
                    <p className="vd-help">Las huellas SHA-256 permiten comprobar que el archivo no fue alterado (por ejemplo, ante una auditoría).</p>
                    <dl className="vd-list">
                        {m.mp4Sha256 && <Hash label="Huella del MP4" value={m.mp4Sha256} />}
                        {m.rawSha256 && <Hash label="Huella del original" value={m.rawSha256} />}
                    </dl>
                </>}
            </details>}
        </section>
    );
}

export function EventDetail({ event: e, videoEdit = null }) {
    const shell = useShell();
    const [editingVideo, setEditingVideo] = useState(false);
    const obs = shell.links.find((l) => l.label === "Emitir con OBS");
    const facts = {
        Tipo: e.type, Inicio: e.date, Organizador: e.organizer, Facultad: e.faculty,
        Carrera: e.career, Lugar: e.place || "Sin definir",
        ...(e.usage ? { "Licencia de uso": e.usage.license, "Referencia de autorización": e.usage.authorization || "Sin referencia registrada" } : {}),
    };
    return (
        <div className="studio-page pg vd">
            {/* Un solo botón principal (ver la sala); el resto, secundarios;
                finalizar va en rojo porque corta la transmisión. */}
            <PageHeader
                crumbs={[["Eventos", shell.links.find((l) => l.label === "Eventos")?.url || "/admin/eventos"], [e.title]]}
                eyebrow={e.category}
                title={e.title}
                description={<><span className={`ph-status studio-status-${e.status}`}>{statusLabel(e)}</span> {e.date}</>}
                actions={<>
                    {e.chatToggleUrl && <form method="POST" action={e.chatToggleUrl}>
                        <input type="hidden" name="_token" value={shell.csrf} />
                        <input type="hidden" name="enabled" value={e.chatEnabled ? "0" : "1"} />
                        <button className="pg-btn" type="submit">{e.chatEnabled ? "Desactivar comentarios" : "Activar comentarios"}</button>
                    </form>}
                    {e.editUrl && <a className="pg-btn" href={e.editUrl}>Editar evento</a>}
                    {videoEdit && <button type="button" className="pg-btn" onClick={() => setEditingVideo(true)}>Editar video</button>}
                    {e.finishUrl && <Action url={e.finishUrl} className="pg-btn is-danger" confirm="¿Finalizar la transmisión y preparar su grabación?">Finalizar transmisión</Action>}
                    <a className="pg-btn is-primary" href={e.url} target="_blank" rel="noopener">Ver sala pública ↗</a>
                </>}
            />
            <Feedback />
            {videoEdit && <ProgramDrawer open={editingVideo} onClose={() => setEditingVideo(false)} editing={videoEdit} mode={videoEdit.mode}
                action={videoEdit.action} csrf={shell.csrf} categories={videoEdit.categories} faculties={videoEdit.faculties} careers={videoEdit.careers} />}
            <div className="vd-grid">
                <div className="vd-main">
                    <MediaPreview event={e} />
                    <article className="vd-card vd-about">
                        <h2>Acerca del evento</h2>
                        <p className="vd-description">{e.description || "Sin descripción."}</p>
                        <dl className="vd-facts">
                            {Object.entries(facts).map(([k, v]) => <div key={k}><dt>{k}</dt><dd>{v || "—"}</dd></div>)}
                        </dl>
                    </article>
                </div>
                <aside className="vd-side" aria-label="Gestión del video">
                    <AuthorizationCard event={e} />
                    <PublicationCard event={e} />
                    <FileCard event={e} />
                    {e.subtitles && (
                        <section className="vd-card vd-side-card react-subtitles" aria-labelledby="vd-sub-title">
                            <h2 id="vd-sub-title" className="vd-eyebrow">Subtítulos</h2>
                            <p className="vd-help">El público los activa con el botón CC del reproductor.</p>
                            <SubtitleForm subtitles={e.subtitles} />
                        </section>
                    )}
                    {obs && !e.scheduled && e.status !== "finalizado" && <section className="vd-card vd-side-card">
                        <h2 className="vd-eyebrow">Transmisión en vivo</h2>
                        <p className="vd-help">La dirección del servidor y la clave para OBS están en «Emitir con OBS».</p>
                        <a className="pg-btn" href={obs.url}>Ir a Emitir con OBS</a>
                    </section>}
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
    const [showKey, setShowKey] = useState(false);
    async function copyValue(label, v) {
        setCopy((await copyText(v)) ? `${label} copiado` : "Selecciona el texto y cópialo manualmente.");
        window.setTimeout(() => setCopy(""), 2500);
    }
    const tone = signal === "Señal recibida" ? "live" : signal === "Transmisión finalizada" ? "finished" : signal.startsWith("○") ? "waiting" : "error";
    const signalText = { live: "En vivo · OBS está enviando señal", finished: "Transmisión finalizada", waiting: e.streamKey ? "Esperando señal de OBS" : "Falta generar la clave", error: signal }[tone];
    const Icon = ({ d }) => <svg className="obs-icon" viewBox="0 0 24 24" aria-hidden="true"><path d={d} /></svg>;
    const COPY = "M9 9h10v10H9zM5 15V5h10";
    const EYE = "M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12Zm9.5 3a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z";
    const EYE_OFF = "M3 3l18 18M10.6 5.6A9.6 9.6 0 0 1 12 5.5C18 5.5 21.5 12 21.5 12a17 17 0 0 1-3.3 4.1M6.3 6.9C3.9 8.6 2.5 12 2.5 12S6 18.5 12 18.5a9 9 0 0 0 4.2-1";
    return (
        <article className={`studio-panel react-stream-card obs is-${tone}`}>
            <header className="obs-head">
                <div className="obs-head__text">
                    <span className="studio-eyebrow">{e.category}</span>
                    <h2>{e.title}</h2>
                </div>
                <p role="status" className={`obs-signal is-${tone}`}><i aria-hidden="true" />{signalText}</p>
            </header>
            <ol className="obs-steps">
                <li className={e.streamKey ? "is-done" : ""}>
                    <span className="obs-steps__n">1</span>
                    <div className="obs-steps__body">
                        <strong>Genera la clave del evento</strong>
                        {e.streamKey ? <small>Lista. Si la compartiste por error, puedes regenerarla.</small> : <small>Es única para este evento y solo la ve el equipo.</small>}
                        {e.generateUrl && <Action url={e.generateUrl} className={e.streamKey ? "pg-btn" : "pg-btn is-primary"} confirm={e.streamKey ? "¿Reemplazar la clave actual de OBS? La anterior dejará de funcionar." : undefined}>{e.streamKey ? "Regenerar clave" : "Generar clave OBS"}</Action>}
                    </div>
                </li>
                <li className={e.streamKey ? "" : "is-locked"}>
                    <span className="obs-steps__n">2</span>
                    <div className="obs-steps__body">
                        <strong>Pégalas en OBS</strong>
                        <small>OBS → Ajustes → Emisión → Servicio: <b>Personalizado</b>.</small>
                        {e.streamKey && <div className="react-credentials obs-creds">
                            <div><small>Servidor</small><code>{e.server}</code>
                                <button type="button" className="studio-icon-action" onClick={() => copyValue("Servidor", e.server)} aria-label="Copiar Servidor"><Icon d={COPY} /><span className="studio-action-tooltip" role="tooltip">Copiar</span></button></div>
                            <div><small>Clave de retransmisión</small><code>{showKey ? e.streamKey : "•".repeat(Math.min(24, e.streamKey.length))}</code>
                                <button type="button" className="studio-icon-action" onClick={() => setShowKey((v) => !v)} aria-label={showKey ? "Ocultar clave" : "Mostrar clave"} aria-pressed={showKey}><Icon d={showKey ? EYE_OFF : EYE} /><span className="studio-action-tooltip" role="tooltip">{showKey ? "Ocultar" : "Mostrar"}</span></button>
                                <button type="button" className="studio-icon-action" onClick={() => copyValue("Clave", e.streamKey)} aria-label="Copiar Clave"><Icon d={COPY} /><span className="studio-action-tooltip" role="tooltip">Copiar</span></button></div>
                            <small role="status" className="obs-copied">{copy}</small>
                        </div>}
                    </div>
                </li>
                <li className={tone === "live" ? "is-done" : e.streamKey ? "" : "is-locked"}>
                    <span className="obs-steps__n">3</span>
                    <div className="obs-steps__body">
                        <strong>Pulsa «Iniciar transmisión» en OBS</strong>
                        <small>{tone === "live" ? "La señal ya llega: el evento está en vivo." : "En unos segundos el estado de arriba cambiará a «En vivo»."}</small>
                    </div>
                </li>
            </ol>
            <div className="react-toolbar obs-actions">
                <a href={e.url} className="pg-btn" target="_blank" rel="noopener">Ver sala ↗</a>
                {e.detailUrl && <a href={e.detailUrl} className="pg-btn">Evento y grabación</a>}
                {e.chatToggleUrl && <form method="POST" action={e.chatToggleUrl}>
                    <Token />
                    <input type="hidden" name="enabled" value={e.chatEnabled ? "0" : "1"} />
                    <button className="pg-btn" type="submit">{e.chatEnabled ? "Desactivar comentarios" : "Activar comentarios"}</button>
                </form>}
                {e.finishUrl && <Action url={e.finishUrl} confirm="¿Finalizar esta transmisión? OBS se desconectará y se preparará la grabación." className="pg-btn is-danger">Finalizar transmisión</Action>}
            </div>
        </article>
    );
}
export function Streaming({ events, pagination }) {
    return (
        <div className="studio-page pg">
            <PageHeader eyebrow="Transmitir" title="Emitir con OBS" description="Eventos en vivo que esperan o reciben señal de OBS. Las grabaciones están en Eventos y los videos pregrabados en Parrilla 24/7." />
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
