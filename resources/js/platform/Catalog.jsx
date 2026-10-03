import React from "react";
import { useShell, Feedback, Pagination, statusLabel, Action } from "./shared";
export function Card({ event: e }) {
    return (
        <article className="catalog-event-entry">
            <a className="uebtv-catalog-card" href={e.url}>
                <div
                    className={`uebtv-catalog-card__visual ${e.image ? "has-image" : ""}`}
                    style={
                        e.image
                            ? {
                                  backgroundImage: `url(${JSON.stringify(e.image)})`,
                              }
                            : undefined
                    }
                >
                    <span className="uebtv-catalog-card__play">▶</span>
                    <span
                        className={`uebtv-catalog-card__status ${e.status === "en_vivo" ? "is-live" : ""}`}
                    >
                        {statusLabel(e)}
                    </span>
                </div>
                <div className="uebtv-catalog-card__body">
                    <span>{e.category}</span>
                    <h3>{e.title}</h3>
                    <p>{e.faculty}</p>
                    <small>{e.date}</small>
                </div>
            </a>
            {e.deleteUrl && (e.status === "finalizado" || e.scheduled) && (
                <div className="catalog-event-delete">
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
}) {
    const shell = useShell();
    const link = (value) =>
        `${shell.catalog}?${new URLSearchParams({ ...filters, filtro: value })}`;
    return (
        <div className="uebtv-catalog-page">
            <div className="uebtv-catalog-container">
                <section className="uebtv-catalog-hero">
                    <div>
                        <p className="uebtv-kicker">
                            PROGRAMACIÓN INSTITUCIONAL
                        </p>
                        <h1>
                            Todo lo que pasa
                            <br />
                            <em>en la UEB.</em>
                        </h1>
                        <p>
                            Transmisiones, conferencias y contenidos para seguir
                            conectados con nuestra comunidad.
                        </p>
                        <a className="studio-button" href={shell.channel}>
                            Ver señal continua ↗
                        </a>
                    </div>
                    <div className="uebtv-catalog-mark">
                        <strong>UEB</strong>
                        <span>TV</span>
                        <i />
                    </div>
                </section>
                <Feedback />
                <form className="uebtv-catalog-search" action={shell.catalog}>
                    <input type="hidden" name="filtro" value={filter} />
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
                </form>
                <div className="uebtv-catalog-toolbar">
                    <div>
                        <h2>Eventos y transmisiones</h2>
                        <p>{pagination.total} resultados</p>
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
