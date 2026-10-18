import React from "react";
import { Feedback, Heading, Pagination } from "./shared";

const labels = { recording: "Grabación en vivo", scheduled: "Programación", ad: "Publicidad" };
const states = { ready: "Disponible", recording: "Grabando", processing: "Procesando", pending: "Pendiente", waiting: "En espera", failed: "Revisar archivo" };
const bytes = (value) => {
    if (!Number.isFinite(value) || value < 0) return "Pendiente";
    const unit = Math.min(4, Math.floor(Math.log(Math.max(value, 1)) / Math.log(1024)));
    return `${new Intl.NumberFormat("es-EC", { maximumFractionDigits: 2 }).format(value / 1024 ** unit)} ${["B", "KiB", "MiB", "GiB", "TiB"][unit]}`;
};

export default function MediaLibrary({ items, pagination, filters, categories }) {
    return <div className="studio-page media-library">
        <Heading eyebrow="ARCHIVOS DEL CANAL" title="Biblioteca de medios">
            Grabaciones, programación y publicidad en un solo lugar. Los videos se cargan únicamente cuando decides reproducirlos.
        </Heading>
        <Feedback />
        <form method="GET" className="studio-panel media-library-filters" aria-label="Filtrar biblioteca">
            <label>Buscar por título<input type="search" name="q" defaultValue={filters.q || ""} maxLength="120" placeholder="Nombre del video" /></label>
            <label>Origen<select name="kind" defaultValue={filters.kind || ""}><option value="">Todos</option>{Object.entries(labels).map(([key, text]) => <option key={key} value={key}>{text}</option>)}</select></label>
            <label>Categoría<select name="categoria_id" defaultValue={filters.categoria_id || ""}><option value="">Todas</option>{categories.map((category) => <option key={category.id} value={category.id}>{category.nombre}</option>)}</select></label>
            <label>Desde<input type="date" name="from" defaultValue={filters.from || ""} /></label>
            <label>Hasta<input type="date" name="to" defaultValue={filters.to || ""} min={filters.from || undefined} /></label>
            <div className="media-library-filters__actions"><button className="studio-button">Aplicar filtros</button><a href="/admin/medios">Limpiar</a></div>
        </form>
        <div className="media-library-summary"><h2>Archivos encontrados</h2><span>{pagination.total} medios</span></div>
        {items.length ? <div className="media-library-grid">
            {items.map((item) => <article className="media-library-card" key={item.key}>
                <div className="media-library-card__visual">
                    {item.previewUrl ? <video controls preload="none" playsInline poster={item.image || undefined} src={item.previewUrl} aria-label={`Vista previa de ${item.title}`} />
                        : item.image ? <img src={item.image} alt="" loading="lazy" />
                        : <span className="media-library-card__placeholder" aria-hidden="true">{item.kind === "ad" ? "P" : "▶"}</span>}
                    <span className="media-library-card__kind">{labels[item.kind]}</span>
                </div>
                <div className="media-library-card__body">
                    <div className="media-library-card__meta"><span>{item.category}</span><time dateTime={item.date}>{item.date}</time></div>
                    <h3>{item.title}</h3>
                    <span className="media-library-card__state">{states[item.status] || item.status}</span>
                    <dl><div><dt>{item.kind === "recording" && !item.sourcePresent ? "Origen procesado" : "Original"}</dt><dd>{item.sourceFormat} · {bytes(item.sourceBytes)}</dd></div><div><dt>Preparado</dt><dd>{item.readyFormat} · {bytes(item.readyBytes)}</dd></div></dl>
                    <a href={item.detailsUrl}>Gestionar archivo ↗</a>
                </div>
            </article>)}
        </div> : <div className="studio-panel studio-empty"><h3>No hay videos con estos filtros</h3><p>Prueba otro período, origen o categoría.</p></div>}
        <Pagination pagination={pagination} />
    </div>;
}
