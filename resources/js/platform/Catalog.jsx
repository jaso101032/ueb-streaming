import React, { useState } from "react";
import { useShell, Feedback, Pagination, statusLabel, Action } from "./shared";
import VideoAnalytics from "./VideoAnalytics";
export function Card({ event: e }) {
    return (
        <article className="catalog-event-entry">
            <div className="uebtv-catalog-card">
                <a
                    className={`uebtv-catalog-card__visual uebtv-catalog-card__visual-link ${e.image ? "has-image" : ""}`}
                    href={e.url}
                    aria-label={`Abrir ${e.title}`}
                    style={e.image ? { backgroundImage: `url(${JSON.stringify(e.image)})` } : undefined}
                >
                    <span className="uebtv-catalog-card__play">▶</span>
                    <span
                        className={`uebtv-catalog-card__status ${e.status === "en_vivo" ? "is-live" : ""}`}
                    >
                        <i aria-hidden="true" />
                        {statusLabel(e)}
                    </span>
                </a>
                <div className="uebtv-catalog-card__body">
                    <div className="uebtv-catalog-card__eyebrow-row">
                        <span>{e.category}</span>
                        {e.analyticsUrl && <VideoAnalytics url={e.analyticsUrl} title={e.title} />}
                    </div>
                    <a className="uebtv-catalog-card__details-link" href={e.url}>
                        <h3>{e.title}</h3>
                        <div className="uebtv-catalog-card__meta">
                            <span>{e.faculty}</span>
                            <time>{e.date}</time>
                        </div>
                    </a>
                </div>
            </div>
            {e.deleteUrl && (e.status === "finalizado" || e.scheduled) && (
                <div className="catalog-event-actions">
                    <Action
                        url={e.deleteUrl}
                        method="DELETE"
                        confirm="¿Eliminar este evento del catálogo?"
                        className="studio-delete"
                    >
                        Eliminar evento
                    </Action>
                </div>
            )}
        </article>
    );
}
export default function Catalog({
    events,
    upcoming,
    pagination,
    filter,
    filters,
    categories,
    faculties,
    isAdmin = false,
}) {
    const shell = useShell();
    const appliedFilters = [
        filters.q,
        filters.facultad_id,
        filters.categoria_id,
    ].filter(Boolean).length;
    const [filtersOpen, setFiltersOpen] = useState(appliedFilters > 0);
    const link = (value) =>
        `${shell.catalog}?${new URLSearchParams({ ...filters, filtro: value })}`;
    return (
        <div className="uebtv-catalog-page">
            <div className="uebtv-catalog-container">
                <section className="uebtv-catalog-hero">
                    <div>
                        <p className="uebtv-kicker">
                            EXPLORA EL CONTENIDO
                        </p>
                        <h1>
                            Catálogo <em>UEBTV.</em>
                        </h1>
                        <p>
                            Eventos en vivo, próximos encuentros y grabaciones
                            de nuestra comunidad.
                        </p>
                        <a className="studio-button" href={shell.channel}>
                            Ir a la señal continua ↗
                        </a>
                    </div>
                    <div className="uebtv-catalog-mark" aria-label="Universidad Estatal de Bolívar">
                        <img
                            src={shell.logo}
                            alt="Universidad Estatal de Bolívar"
                            width="2000"
                            height="420"
                        />
                    </div>
                </section>
                <Feedback />
                <form
                    className={`uebtv-catalog-search ${filtersOpen ? "is-open" : ""}`}
                    action={shell.catalog}
                >
                    <input type="hidden" name="filtro" value={filter} />
                    <button
                        type="button"
                        className="uebtv-catalog-filter-toggle"
                        aria-expanded={filtersOpen}
                        aria-controls="catalog-search-fields"
                        onClick={() => setFiltersOpen((open) => !open)}
                    >
                        <span aria-hidden="true">☷</span>
                        Filtrar catálogo
                        {appliedFilters > 0 && (
                            <small>{appliedFilters}</small>
                        )}
                        <b aria-hidden="true">⌄</b>
                    </button>
                    <div
                        id="catalog-search-fields"
                        className="uebtv-catalog-search-fields"
                    >
                        <div>
                            <label htmlFor="q">Buscar por título</label>
                            <input
                                id="q"
                                type="search"
                                name="q"
                                maxLength={120}
                                defaultValue={filters.q || ""}
                                placeholder="¿Qué quieres ver?"
                            />
                        </div>
                        {[
                            [
                                "facultad_id",
                                "Facultad",
                                faculties,
                                "Todas las facultades",
                            ],
                            [
                                "categoria_id",
                                "Categoría",
                                categories,
                                "Todas las categorías",
                            ],
                        ].map(([name, label, options, placeholder]) => (
                            <div key={name}>
                                <label htmlFor={name}>{label}</label>
                                <select
                                    id={name}
                                    name={name}
                                    defaultValue={filters[name] || ""}
                                >
                                    <option value="">{placeholder}</option>
                                    {options.map((o) => (
                                        <option key={o.id} value={o.id}>
                                            {o.nombre}
                                        </option>
                                    ))}
                                </select>
                            </div>
                        ))}
                        <button>Buscar</button>
                        <a href={shell.catalog}>Limpiar filtros</a>
                    </div>
                </form>
                <div className="uebtv-catalog-toolbar">
                    <div>
                        <h2>Eventos y transmisiones</h2>
                        <p>{pagination.total} resultados{isAdmin && <span className="uebtv-catalog-sort-note"><i aria-hidden="true">↓</i> Más vistos primero</span>}</p>
                    </div>
                    <nav className="uebtv-catalog-filters">
                        {Object.entries({
                            todos: "Todos",
                            en_vivo: "En vivo",
                            anteriores: "Anteriores",
                            proximos: "Próximos",
                        }).map(([key, label]) => (
                            <a
                                key={key}
                                href={link(key)}
                                className={filter === key ? "is-active" : ""}
                                aria-current={
                                    filter === key ? "page" : undefined
                                }
                            >
                                {label}
                            </a>
                        ))}
                    </nav>
                </div>
                <section className="uebtv-catalog-grid">
                    {events.length ? (
                        events.map((e) => <Card key={e.id} event={e} />)
                    ) : (
                        <p className="uebtv-library-empty">
                            No encontramos eventos. Prueba otro título o cambia
                            los filtros.
                        </p>
                    )}
                </section>
                <Pagination pagination={pagination} />
                {filter !== "proximos" && (
                    <section className="uebtv-upcoming">
                        <div className="uebtv-catalog-toolbar">
                            <h2>Próximos eventos</h2>
                            <a href={link("proximos")}>
                                Ver todos los próximos →
                            </a>
                        </div>
                        <div className="uebtv-upcoming-grid">
                            {upcoming.length ? (
                                upcoming.map((e) => (
                                    <a
                                        key={e.id}
                                        className="uebtv-upcoming-card"
                                        href={e.url}
                                    >
                                        <time>{e.date}</time>
                                        <h3>{e.title}</h3>
                                        <p>{e.faculty}</p>
                                        <span>{e.category}</span>
                                    </a>
                                ))
                            ) : (
                                <p>
                                    No hay próximos eventos que coincidan con
                                    estos filtros.
                                </p>
                            )}
                        </div>
                    </section>
                )}
            </div>
        </div>
    );
}
