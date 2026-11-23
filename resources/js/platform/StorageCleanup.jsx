import React, { useEffect, useMemo, useRef, useState } from "react";
import { createPortal } from "react-dom";
import { askConfirm, Token } from "./shared";

/*
 * "Liberar espacio": una sola lista con lo que se puede eliminar, del más
 * pesado al más liviano. Eliminar es borrar todo (el evento o la publicidad
 * con su video y archivos). Siempre muestra cuánto espacio se recupera antes
 * de confirmar y permite descargar una copia antes de borrar.
 */
const TYPE = { catalog: "Video del catálogo", recording: "Grabación", scheduled: "Video de la parrilla", ad: "Publicidad" };

const human = (value) => {
    const bytes = Math.max(0, Number(value) || 0);
    if (bytes >= 1024 ** 3) return `${(bytes / 1024 ** 3).toLocaleString("es-EC", { maximumFractionDigits: 1 })} GB`;
    if (bytes >= 1024 ** 2) return `${Math.round(bytes / 1024 ** 2).toLocaleString("es-EC")} MB`;
    return `${Math.max(1, Math.round(bytes / 1024)).toLocaleString("es-EC")} KB`;
};
const shortDate = (iso) => {
    const date = new Date(`${iso}T12:00:00`);
    return Number.isNaN(date.getTime()) ? "" : date.toLocaleDateString("es-EC", { day: "numeric", month: "short", year: "numeric" });
};

function Icon({ name }) {
    const p = {
        close: <path d="M6 6l12 12M18 6 6 18" />,
        disk: <><ellipse cx="12" cy="6" rx="8" ry="3" /><path d="M4 6v12c0 1.7 3.6 3 8 3s8-1.3 8-3V6M4 12c0 1.7 3.6 3 8 3s8-1.3 8-3" /></>,
        layers: <path d="m12 3 9 5-9 5-9-5zM3 13l9 5 9-5" />,
        trash: <><path d="M4 7h16M10 11v6m4-6v6" /><path d="M6 7l1 12a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2l1-12M9 7V4h6v3" /></>,
        bin: <><path d="M3 7h18l-2 13H5zM8 7V4h8v3" /></>,
        check: <path d="m5 12 4.5 4.5L19 7" />,
        info: <><circle cx="12" cy="12" r="9" /><path d="M12 11v5m0-8v.1" /></>,
        download: <path d="M12 4v11m-5-5 5 5 5-5M5 20h14" />,
        eye: <><path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12Z" /><circle cx="12" cy="12" r="3" /></>,
    };
    return <svg className="fs-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">{p[name]}</svg>;
}

export default function StorageCleanup({ cleanup, storage: initialStorage, onClose }) {
    // La lista se pide al abrir la ventana: medir los archivos tarda y no hace
    // falta en cada visita a la Biblioteca.
    const [data, setData] = useState(null);
    const [failed, setFailed] = useState(false);
    useEffect(() => {
        let alive = true;
        fetch(cleanup.listUrl, { headers: { Accept: "application/json" }, credentials: "same-origin" })
            .then((r) => (r.ok ? r.json() : Promise.reject(r)))
            .then((json) => { if (alive) setData(json); })
            .catch(() => { if (alive) setFailed(true); });
        return () => { alive = false; };
    }, [cleanup.listUrl]);
    const items = data?.items || [];
    const storage = data?.storage || initialStorage;
    const [selected, setSelected] = useState(() => new Set());
    const [sort, setSort] = useState("bytes");
    const [busy, setBusy] = useState(false);
    const form = useRef(null);
    const panel = useRef(null);

    useEffect(() => {
        const previous = document.activeElement;
        const overflow = document.body.style.overflow;
        document.body.style.overflow = "hidden";
        panel.current?.focus();
        const key = (event) => { if (event.key === "Escape" && !document.querySelector(".cf-backdrop")) onClose(); };
        document.addEventListener("keydown", key);
        return () => { document.removeEventListener("keydown", key); document.body.style.overflow = overflow; previous?.focus?.({ preventScroll: true }); };
    }, [onClose]);

    const list = useMemo(() => {
        const rows = [...items];
        if (sort === "views") rows.sort((a, b) => (a.views ?? -1) - (b.views ?? -1) || b.bytes - a.bytes);
        if (sort === "old") rows.sort((a, b) => String(a.date).localeCompare(String(b.date)));
        return rows;
    }, [items, sort]);
    const chosen = items.filter((i) => selected.has(i.key));
    const total = chosen.reduce((sum, i) => sum + i.bytes, 0);
    const allChecked = list.length > 0 && list.every((i) => selected.has(i.key));
    const toggle = (key) => setSelected((current) => { const next = new Set(current); next.has(key) ? next.delete(key) : next.add(key); return next; });
    const toggleAll = () => setSelected(allChecked ? new Set() : new Set(list.map((i) => i.key)));

    const submit = async () => {
        if (!chosen.length || busy) return;
        const what = chosen.length === 1 ? `«${chosen[0].title}»` : `${chosen.length} elementos`;
        const message = `¿Eliminar ${what}? Se borra con su video y todos sus archivos, y se liberarán ${human(total)}. No se puede deshacer.`;
        if (!(await askConfirm(message, { confirmLabel: `Eliminar ${human(total)}`, danger: true }))) return;
        setBusy(true);
        form.current?.submit();
    };

    return createPortal(
        <div className="fs-backdrop" onMouseDown={(event) => { if (event.target === event.currentTarget && !busy) onClose(); }}>
            <section ref={panel} tabIndex="-1" className="fs" role="dialog" aria-modal="true" aria-labelledby="fs-title" aria-describedby="fs-lead">
                <header className="fs-head">
                    <span className="fs-head__icon"><Icon name="disk" /></span>
                    <div>
                        <h2 id="fs-title">Liberar espacio</h2>
                        <p id="fs-lead">{storage?.available ? <>Libres ahora: <strong>{human(storage.freeBytes)}</strong> de {human(storage.totalBytes)}. Elige qué eliminar.</> : "Elige qué eliminar."}</p>
                    </div>
                    <button type="button" className="fs-close" onClick={onClose} aria-label="Cerrar Liberar espacio"><Icon name="close" /></button>
                </header>

                <div className="fs-body" id="fs-panel">
                    <p className="fs-explain is-danger"><span className="fs-badge">Eliminar es borrar todo</span>Se borra el evento o la publicidad con su video, la grabación original, las calidades y los subtítulos. No se puede deshacer: si quieres conservar algo, pulsa «Descargar» antes.</p>
                    {!data ? <div className="fs-empty" role="status">{failed
                        ? <p>No se pudo cargar la lista. Cierra la ventana e inténtalo de nuevo.</p>
                        : <><span className="fs-spinner" aria-hidden="true" /><p>Revisando los archivos del servidor…</p></>}</div>
                    : list.length ? <>
                        <div className="fs-toolbar">
                            <label className="fs-check fs-check--all"><input type="checkbox" checked={allChecked} onChange={toggleAll} /><span>Elegir todo ({list.length})</span></label>
                            <div className="fs-sort" role="group" aria-label="Ordenar">
                                <button type="button" aria-pressed={sort === "bytes"} onClick={() => setSort("bytes")}>Más pesados</button>
                                <button type="button" aria-pressed={sort === "views"} onClick={() => setSort("views")}>Menos vistos</button>
                                <button type="button" aria-pressed={sort === "old"} onClick={() => setSort("old")}>Más antiguos</button>
                            </div>
                        </div>
                        <ul className="fs-list">
                            {list.map((item) => <li key={item.key} className={selected.has(item.key) ? "is-selected" : ""}>
                                <label className="fs-row">
                                    <input type="checkbox" checked={selected.has(item.key)} onChange={() => toggle(item.key)} aria-label={`Elegir ${item.title} (${item.size})`} />
                                    <span className={`fs-thumb ${item.image ? "has-image" : ""}`} style={item.image ? { backgroundImage: `url(${JSON.stringify(item.image)})` } : undefined} aria-hidden="true">{!item.image && <img src="/images/brand/ueb-wordmark.png" alt="" />}</span>
                                    <span className="fs-info">
                                        <strong title={item.title}>{item.title}</strong>
                                        <small>{TYPE[item.type] || "Video"} · {item.category}{item.date ? ` · ${shortDate(item.date)}` : ""}{item.views !== null && item.views !== undefined ? <> · <Icon name="eye" />{item.views} {item.views === 1 ? "vista" : "vistas"} en {data?.viewsDays ?? 60} días</> : null}</small>
                                        {item.tags?.length > 0 && <span className="fs-tags">{item.tags.map((t) => <i key={t}>{t}</i>)}</span>}
                                    </span>
                                    <b className="fs-size">{item.size}</b>
                                </label>
                                {item.downloadUrl && <a className="fs-dl" href={item.downloadUrl} aria-label={`Descargar una copia de ${item.title}`} title="Descargar una copia antes de borrar"><Icon name="download" /><span>Descargar</span></a>}
                            </li>)}
                        </ul>
                    </> : <div className="fs-empty"><span><Icon name="check" /></span><p>No hay nada que se pueda eliminar por ahora.</p></div>}
                    <p className="fs-note"><Icon name="info" />No aparece lo que está en vivo o procesándose, los programas de la parrilla por emitir ni la publicidad activa (desactívala primero). Todo queda registrado en Auditoría.</p>
                </div>

                <footer className="fs-foot">
                    <p aria-live="polite">{chosen.length
                        ? <><strong>{chosen.length} elegido{chosen.length === 1 ? "" : "s"}</strong> · Liberarás <strong className="fs-total">{human(total)}</strong></>
                        : "Elige lo que quieres eliminar"}</p>
                    <div className="fs-foot__actions">
                        <button type="button" className="fs-btn" onClick={onClose} disabled={busy}>Cancelar</button>
                        <button type="button" className="fs-btn is-danger" onClick={submit} disabled={!chosen.length || busy}><Icon name="trash" />{busy ? "Eliminando…" : chosen.length ? `Eliminar ${human(total)}` : "Eliminar"}</button>
                    </div>
                    <form ref={form} method="POST" action={cleanup.url} hidden>
                        <Token />
                        {chosen.map((i) => <input key={i.key} type="hidden" name="items[]" value={i.key} />)}
                    </form>
                </footer>
            </section>
        </div>,
        document.body,
    );
}
