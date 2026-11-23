import React, { useEffect, useRef, useState } from "react";
import { Feedback, Pagination, useShell } from "./shared";
import { ProgramDrawer } from "../components/ScheduledStudio";
import StorageCleanup from "./StorageCleanup";

/*
 * Biblioteca de medios: el inventario de archivos de video guardados en el
 * servidor (solo para el equipo). No es la vitrina: para eso está el
 * catálogo público. Por eso se muestra como una lista de archivos con su
 * tipo, estado y peso, y el video solo se carga al pulsar "Reproducir".
 */
const KINDS = {
    recording: { label: "Grabación de directo", short: "Grabaciones", one: "Grabación", icon: "rec" },
    catalog: { label: "Video del catálogo", short: "Videos", one: "Video", icon: "film" },
    scheduled: { label: "Video programado", short: "Programación", one: "Programado", icon: "clock" },
    ad: { label: "Publicidad", short: "Publicidad", one: "Publicidad", icon: "megaphone" },
};
const STATES = {
    ready: ["Disponible", "ok"], recording: ["Grabando", "live"], processing: ["Procesando", "work"], pending: ["En cola", "work"],
    waiting: ["Esperando señal", "work"], failed: ["Revisar archivo", "bad"],
};
const bytes = (value) => {
    if (!Number.isFinite(value) || value <= 0) return "—";
    const unit = Math.min(4, Math.floor(Math.log(Math.max(value, 1)) / Math.log(1024)));
    return `${new Intl.NumberFormat("es-EC", { maximumFractionDigits: unit > 2 ? 2 : 0 }).format(value / 1024 ** unit)} ${["B", "KB", "MB", "GB", "TB"][unit]}`;
};
const bytesFree = (value) => (value > 0 ? bytes(value) : "0 MB");
const longDate = (iso) => {
    const date = new Date(`${iso}T12:00:00`);
    return Number.isNaN(date.getTime()) ? iso : date.toLocaleDateString("es-EC", { day: "numeric", month: "short", year: "numeric" });
};

function MIcon({ name }) {
    const paths = {
        rec: <><circle cx="12" cy="12" r="8.5" /><circle cx="12" cy="12" r="3.5" fill="currentColor" stroke="none" /></>,
        clock: <><circle cx="12" cy="12" r="8.5" /><path d="M12 7.5V12l3 2" /></>,
        megaphone: <path d="M4 10v4h3l7 4V6l-7 4H4Zm13-1.5a4 4 0 0 1 0 7" />,
        play: <path d="m9 7 8 5-8 5z" />,
        eye: <><path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12Z" /><circle cx="12" cy="12" r="3" /></>,
        gear: <><circle cx="12" cy="12" r="3" /><path d="M12 3v2m0 14v2M3 12h2m14 0h2M5.6 5.6l1.4 1.4m10 10 1.4 1.4m0-12.8-1.4 1.4m-10 10-1.4 1.4" /></>,
        box: <><path d="M3.5 7.5 12 3l8.5 4.5v9L12 21l-8.5-4.5z" /><path d="M3.5 7.5 12 12l8.5-4.5M12 12v9" /></>,
        search: <><circle cx="11" cy="11" r="6.5" /><path d="m20 20-4.2-4.2" /></>,
        close: <path d="M6 6l12 12M18 6 6 18" />,
        arrow: <path d="M7 17 17 7M9 7h8v8" />,
        download: <path d="M12 4v11m-5-5 5 5 5-5M5 20h14" />,
        film: <><rect x="3.5" y="5" width="17" height="14" rx="2" /><path d="M8 5v14m8-14v14M3.5 9.5H8m8 0h4.5m-17 5H8m8 0h4.5" /></>,
        upload: <path d="M12 16V4m-5 5 5-5 5 5M5 20h14" />,
        disk: <><ellipse cx="12" cy="6" rx="8" ry="3" /><path d="M4 6v12c0 1.7 3.6 3 8 3s8-1.3 8-3V6M4 12c0 1.7 3.6 3 8 3s8-1.3 8-3" /></>,
    };
    return <svg className="ml-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">{paths[name]}</svg>;
}

function Player({ item, onClose }) {
    const dialog = useRef(null);
    useEffect(() => {
        dialog.current?.showModal?.();
        const node = dialog.current;
        const closed = () => onClose();
        node?.addEventListener("close", closed);
        return () => node?.removeEventListener("close", closed);
    }, [onClose]);
    return (
        <dialog ref={dialog} className="ml-player" aria-label={`Vista previa de ${item.title}`} onClick={(event) => { if (event.target === dialog.current) dialog.current.close(); }}>
            <div className="ml-player__head">
                <strong>{item.title}</strong>
                <button type="button" onClick={() => dialog.current?.close()} aria-label="Cerrar vista previa"><MIcon name="close" /></button>
            </div>
            <video src={item.previewUrl} poster={item.image || undefined} controls autoPlay playsInline />
        </dialog>
    );
}

export default function MediaLibrary({ items, pagination, filters, categories, counts = {}, totalBytes = 0, catalogUrl = "/eventos", storage = null, cleanup = null, upload = null }) {
    const shell = useShell();
    const [playing, setPlaying] = useState(null);
    const [uploading, setUploading] = useState(Boolean(upload?.open));
    const closeUpload = React.useCallback(() => setUploading(false), []);
    // Mientras haya videos preparándose, la página se actualiza sola.
    const working = items.some((item) => ["pending", "processing"].includes(item.status));
    useEffect(() => {
        if (!working) return undefined;
        const timer = window.setInterval(() => {
            if (document.visibilityState === "visible" && !document.querySelector(".fs-backdrop, .sp-drawer.is-open, .ml-player[open]")) window.location.reload();
        }, 20000);
        return () => window.clearInterval(timer);
    }, [working]);
    const [freeing, setFreeing] = useState(Boolean(cleanup?.open));
    useEffect(() => {
        // "?liberar=1" y "?subir=1" abren su ventana una vez; no se repiten al recargar.
        if (!cleanup?.open && !upload?.open) return;
        const url = new URL(window.location.href);
        url.searchParams.delete("liberar");
        url.searchParams.delete("subir");
        window.history.replaceState(null, "", url);
    }, [cleanup, upload]);
    const closeFreeing = React.useCallback(() => setFreeing(false), []);
    const level = storage?.available ? storage.level : "ok";
    const kind = filters.kind || "";
    const query = (next) => {
        const params = new URLSearchParams();
        for (const [key, value] of Object.entries({ ...filters, ...next })) if (value) params.set(key, value);
        const text = params.toString();
        return `/admin/medios${text ? `?${text}` : ""}`;
    };
    return <div className="studio-page media-library ml">
        <header className="ml-head">
            <div>
                <p className="ml-eyebrow"><MIcon name="box" />Solo para el equipo</p>
                <h1>Biblioteca de medios</h1>
                <p className="ml-lead">Todos los archivos de video guardados en el servidor, incluso los que el público no ve. Aquí revisas su estado y cuánto espacio ocupan.</p>
            </div>
            <div className="ml-head__side">
                {upload && <button type="button" className="ml-upload" onClick={() => setUploading(true)}><MIcon name="upload" />Subir video</button>}
                <a className="ml-public" href={catalogUrl} target="_blank" rel="noopener">
                    <MIcon name="eye" />
                    <span><strong>¿Quieres ver lo que ve la comunidad?</strong><small>Abre el Catálogo público</small></span>
                    <MIcon name="arrow" />
                </a>
                {cleanup && <button type="button" className={`ml-free is-${level}`} onClick={() => setFreeing(true)}>
                    <MIcon name="disk" />
                    <span><strong>Liberar espacio</strong><small>{storage?.available ? `${bytesFree(storage.freeBytes)} libres` : "Borrar archivos que no hacen falta"}</small></span>
                </button>}
            </div>
        </header>
        <Feedback />
        <div className="ml-stats">
            <div><span>Archivos</span><strong>{counts.all ?? pagination.total}</strong></div>
            <div><span>Espacio ocupado</span><strong>{bytes(totalBytes)}</strong></div>
            {storage?.available && <div className={`ml-stats__free is-${level}`}><span>Libre en el servidor</span><strong>{bytesFree(storage.freeBytes)}</strong></div>}
            {Object.entries(KINDS).map(([key, k]) => <div key={key}><span><MIcon name={k.icon} />{k.short}</span><strong>{counts[key] ?? 0}</strong></div>)}
        </div>
        <nav className="ml-tabs" aria-label="Tipo de archivo">
            <a href={query({ kind: "", page: "" })} className={!kind ? "is-active" : ""} aria-current={!kind ? "page" : undefined}>Todos <b>{counts.all ?? 0}</b></a>
            {Object.entries(KINDS).map(([key, k]) => <a key={key} href={query({ kind: key, page: "" })} className={kind === key ? "is-active" : ""} aria-current={kind === key ? "page" : undefined}><MIcon name={k.icon} />{k.short} <b>{counts[key] ?? 0}</b></a>)}
        </nav>
        <form method="GET" className="ml-filters" aria-label="Filtrar biblioteca">
            {kind && <input type="hidden" name="kind" value={kind} />}
            <label className="ml-filters__search"><span className="ct-sr">Buscar por título</span><MIcon name="search" /><input type="search" name="q" defaultValue={filters.q || ""} maxLength="120" placeholder="Buscar por título" aria-label="Buscar por título" /></label>
            <label><span>Categoría</span><select name="categoria_id" defaultValue={filters.categoria_id || ""}><option value="">Todas</option>{categories.map((category) => <option key={category.id} value={category.id}>{category.nombre}</option>)}</select></label>
            <label><span>Desde</span><input type="date" name="from" defaultValue={filters.from || ""} /></label>
            <label><span>Hasta</span><input type="date" name="to" defaultValue={filters.to || ""} min={filters.from || undefined} /></label>
            <div className="ml-filters__actions"><button className="studio-button">Aplicar filtros</button><a href={query({ q: "", categoria_id: "", from: "", to: "", page: "" })}>Limpiar</a></div>
        </form>
        {items.length ? <div className="ml-table" role="table" aria-label="Archivos">
            <div className="ml-thead" role="row">
                <span role="columnheader">Archivo</span>
                <span role="columnheader">Tipo</span>
                <span role="columnheader">Estado</span>
                <span role="columnheader">Peso</span>
                <span role="columnheader" className="ml-thead__actions">Acciones</span>
            </div>
            {items.map((item) => {
                const k = KINDS[item.kind] || KINDS.recording;
                const [stateText, stateTone] = STATES[item.status] || [item.status, "work"];
                const saved = item.sourceBytes > 0 && item.readyBytes > 0 && item.readyBytes < item.sourceBytes ? Math.round((1 - item.readyBytes / item.sourceBytes) * 100) : null;
                const sourceGone = item.kind === "recording" && !item.sourcePresent;
                const original = sourceGone ? "No se conserva" : item.sourceBytes > 0 ? `${item.sourceFormat && item.sourceFormat !== "Original" ? `${item.sourceFormat} · ` : ""}${bytes(item.sourceBytes)}` : "Sin dato";
                const ready = item.readyBytes > 0 ? `${item.readyFormat} · ${bytes(item.readyBytes)}` : item.status === "ready" ? "Sin dato" : "Pendiente";
                return <div className="ml-tr" role="row" key={item.key}>
                    <div className="ml-cell ml-cell--file" role="cell">
                        <button type="button" className={`ml-thumb ${item.image ? "has-image" : ""}`} style={item.image ? { backgroundImage: `url(${JSON.stringify(item.image)})` } : undefined}
                            disabled={!item.previewUrl} onClick={() => setPlaying(item)} aria-label={item.previewUrl ? `Reproducir ${item.title}` : `${item.title}: aún no se puede reproducir`}>
                            {!item.image && <img src="/images/brand/ueb-wordmark.png" alt="" />}
                            {item.previewUrl && <span className="ml-thumb__play"><MIcon name="play" /></span>}
                        </button>
                        <span className="ml-info"><h3 title={item.title}>{item.title}</h3><p>{item.category} · {longDate(item.date)}</p></span>
                    </div>
                    <div className="ml-cell" role="cell"><span className={`ml-kind is-${item.kind}`}><MIcon name={k.icon} />{k.one}</span></div>
                    <div className="ml-cell" role="cell"><span className={`ml-state is-${stateTone}`}><i aria-hidden="true" />{stateText}{item.status === "processing" && item.progress != null ? ` · ${item.progress}%` : ""}</span>{["pending", "processing"].includes(item.status) && item.kind === "catalog" && <small className="ml-progress">Aparecerá en el catálogo al terminar</small>}</div>
                    <div className="ml-cell ml-size" role="cell">
                        <span><small>Original</small>{original}</span>
                        <span><small>{item.kind === "catalog" ? "Para ver" : "Para emitir"}</small>{ready}{saved ? <em>−{saved} %</em> : null}</span>
                    </div>
                    <div className="ml-cell ml-actions" role="cell">
                        {item.previewUrl
                            ? <button type="button" className="studio-icon-action is-primary" onClick={() => setPlaying(item)} aria-label={`Reproducir ${item.title}`}><MIcon name="play" /><span className="studio-action-tooltip" role="tooltip">Reproducir</span></button>
                            : <span className="ml-slot" aria-hidden="true" />}
                        {item.publicUrl
                            ? <a className="studio-icon-action" href={item.publicUrl} target="_blank" rel="noopener" aria-label={`Ver ${item.title} como público`}><MIcon name="eye" /><span className="studio-action-tooltip" role="tooltip">Ver como público</span></a>
                            : <span className="ml-slot" aria-hidden="true" />}
                        {item.downloadUrl
                            ? <a className="studio-icon-action" href={item.downloadUrl} aria-label={`Descargar ${item.title}`}><MIcon name="download" /><span className="studio-action-tooltip" role="tooltip">Descargar</span></a>
                            : <span className="ml-slot" aria-hidden="true" />}
                        <a className="studio-icon-action" href={item.detailsUrl} aria-label={`Gestionar ${item.title}`}><MIcon name="gear" /><span className="studio-action-tooltip" role="tooltip">Gestionar</span></a>
                    </div>
                </div>;
            })}
        </div> : <div className="ml-empty"><MIcon name="box" /><h3>No hay archivos con estos filtros</h3><p>Prueba otro tipo, categoría o período.</p></div>}
        <Pagination pagination={pagination} />
        {playing && <Player item={playing} onClose={() => setPlaying(null)} />}
        {upload && <ProgramDrawer mode="catalog" open={uploading} onClose={closeUpload} action={upload.action} csrf={shell.csrf}
            categories={categories} faculties={upload.faculties} careers={upload.careers} uploadLimits={upload.limits} old={shell.old || {}} />}
        {freeing && cleanup && <StorageCleanup cleanup={cleanup} storage={storage} onClose={closeFreeing} />}
    </div>;
}
