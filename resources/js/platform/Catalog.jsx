import React, { useEffect, useId, useRef, useState } from "react";
import { useShell, Feedback, Pagination, statusLabel, Action } from "./shared";
import VideoAnalytics from "./VideoAnalytics";

function CtIcon({ name }) {
    const paths = {
        search: <><circle cx="11" cy="11" r="6.5" /><path d="m20 20-4.2-4.2" /></>,
        filter: <path d="M4 6h16M7 12h10M10 18h4" />,
        close: <path d="M6 6l12 12M18 6 6 18" />,
        play: <path d="m9 7 8 5-8 5z" />,
        calendar: <><rect x="3.5" y="5" width="17" height="15" rx="2.5" /><path d="M8 3v4m8-4v4M3.5 10h17" /></>,
        arrow: <path d="M7 17 17 7M9 7h8v8" />,
    };
    return <svg className="ct-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">{paths[name]}</svg>;
}

export function Card({ event: e }) {
    const live = e.status === "en_vivo";
    return (
        <article className="ct-card catalog-event-entry">
            <a className={`ct-card__thumb ${e.image ? "has-image" : ""}`} href={e.url} tabIndex={-1} aria-hidden="true"
                style={e.image ? { backgroundImage: `url(${JSON.stringify(e.image)})` } : undefined}>
                {!e.image && <img src="/images/brand/ueb-wordmark.png" alt="" className="ct-card__logo" />}
                <span className={`ct-card__status ${live ? "is-live" : ""}`}>{live ? <i aria-hidden="true" /> : <CtIcon name="play" />}{statusLabel(e)}</span>
            </a>
            <div className="ct-card__body">
                <a className="ct-card__title" href={e.url}><h3>{e.title}</h3></a>
                <p className="ct-card__meta">{[e.category, e.date].filter(Boolean).join(" · ")}</p>
                {e.faculty && <p className="ct-card__faculty">{e.faculty}</p>}
                {(e.analyticsUrl || (e.deleteUrl && (e.status === "finalizado" || e.scheduled))) && <div className="ct-card__tools">
                    {e.analyticsUrl && <VideoAnalytics url={e.analyticsUrl} title={e.title} compact />}
                    {e.deleteUrl && (e.status === "finalizado" || e.scheduled) && <Action url={e.deleteUrl} method="DELETE" confirm={`¿Eliminar «${e.title}» del catálogo? Se borrarán también su grabación y comentarios.`} className="studio-icon-action is-danger" ariaLabel={`Eliminar evento ${e.title}`} tooltip="Eliminar">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M10 11v6m4-6v6" /><path d="M6 7l1 12a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2l1-12M9 7V4h6v3" /></svg>
                    </Action>}
                </div>}
            </div>
        </article>
    );
}

/** Buscador con sugerencias mientras se escribe (combobox accesible). */
function SearchBox({ defaultValue, suggestUrl }) {
    const [value, setValue] = useState(defaultValue);
    const [items, setItems] = useState([]);
    const [open, setOpen] = useState(false);
    const [active, setActive] = useState(-1);
    const id = useId(), listId = `${id}-list`, input = useRef(null);
    useEffect(() => {
        const term = value.trim();
        if (term.length < 2 || term === defaultValue.trim() && !open) { setItems([]); return undefined; }
        const controller = new AbortController();
        const timer = window.setTimeout(() => {
            fetch(`${suggestUrl}?${new URLSearchParams({ q: term })}`, { headers: { Accept: "application/json" }, signal: controller.signal })
                .then((r) => (r.ok ? r.json() : []))
                .then((list) => { setItems(Array.isArray(list) ? list : []); setActive(-1); })
                .catch(() => {});
        }, 180);
        return () => { window.clearTimeout(timer); controller.abort(); };
    }, [value, suggestUrl]); // eslint-disable-line react-hooks/exhaustive-deps
    const showList = open && items.length > 0;
    const go = (item) => { window.location.assign(item.url); };
    const onKeyDown = (event) => {
        if (!showList) return;
        if (event.key === "ArrowDown") { event.preventDefault(); setActive((i) => (i + 1) % items.length); }
        else if (event.key === "ArrowUp") { event.preventDefault(); setActive((i) => (i <= 0 ? items.length - 1 : i - 1)); }
        else if (event.key === "Enter" && active >= 0) { event.preventDefault(); go(items[active]); }
        else if (event.key === "Escape") { setOpen(false); setActive(-1); }
    };
    const highlight = (title) => {
        const term = value.trim();
        const at = title.normalize("NFD").replace(/[\u0300-\u036f]/g, "").toLowerCase().indexOf(term.normalize("NFD").replace(/[\u0300-\u036f]/g, "").toLowerCase());
        return at < 0 || !term ? title : <>{title.slice(0, at)}<mark>{title.slice(at, at + term.length)}</mark>{title.slice(at + term.length)}</>;
    };
    return (
        <div className="ct-search__field" onBlur={(event) => { if (!event.currentTarget.contains(event.relatedTarget)) setOpen(false); }}>
            <label htmlFor="q" className="ct-sr">Buscar por título</label>
            <CtIcon name="search" />
            <input ref={input} id="q" type="search" name="q" maxLength={120} autoComplete="off" spellCheck="false"
                placeholder="Buscar videos y eventos" value={value}
                onChange={(event) => { setValue(event.target.value); setOpen(true); }}
                onFocus={() => setOpen(true)} onKeyDown={onKeyDown}
                role="combobox" aria-autocomplete="list" aria-expanded={showList} aria-controls={listId}
                aria-activedescendant={showList && active >= 0 ? `${id}-opt-${active}` : undefined} />
            {value && <button type="button" className="ct-search__clear" aria-label="Borrar búsqueda" onClick={() => { setValue(""); setItems([]); input.current?.focus(); }}><CtIcon name="close" /></button>}
            <ul id={listId} role="listbox" aria-label="Sugerencias" className="ct-suggest" hidden={!showList}>
                {items.map((item, index) => (
                    <li key={item.id} id={`${id}-opt-${index}`} role="option" aria-selected={index === active}
                        className={index === active ? "is-active" : ""}
                        onMouseDown={(event) => event.preventDefault()} onClick={() => go(item)} onMouseEnter={() => setActive(index)}>
                        <span className="ct-suggest__thumb" style={item.image ? { backgroundImage: `url(${JSON.stringify(item.image)})` } : undefined}>
                            {!item.image && <img src="/images/brand/ueb-wordmark.png" alt="" />}
                            {item.live && <span className="ct-suggest__live">En vivo</span>}
                        </span>
                        <span className="ct-suggest__text">
                            <span className="ct-suggest__title">{highlight(item.title)}</span>
                            <small>{[item.category, item.date].filter(Boolean).join(" · ")}</small>
                        </span>
                    </li>
                ))}
            </ul>
        </div>
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
    careers = [],
    isAdmin = false,
}) {
    const shell = useShell();
    const applied = ["facultad_id", "carrera_id", "categoria_id"].filter((key) => filters[key]).length;
    const facultyCareers = careers.filter((c) => String(c.facultad_id) === String(filters.facultad_id || ""));
    const [filtersOpen, setFiltersOpen] = useState(applied > 0);
    const form = useRef(null);
    const link = (value) => `${shell.catalog}?${new URLSearchParams({ ...filters, filtro: value })}`;
    const tabs = { todos: "Todos", en_vivo: "En vivo", anteriores: "Anteriores", proximos: "Próximos" };
    return (
        <div className="uebtv-catalog-page">
            <div className="ct">
                <header className="ct-hero">
                    <div className="ct-hero__text">
                        <p className="ct-kicker">EXPLORA EL CONTENIDO</p>
                        <h1 className="uebtv-catalog-title" aria-label="Catálogo UEB - TV">
                            <span>Catálogo</span>
                            <span className="channel-heading-title__mark" aria-hidden="true"><img src="/images/brand/ueb-wordmark.png" alt="" /></span>
                            <span className="channel-heading-title__suffix">- TV</span>
                        </h1>
                        <p>Directos, próximos encuentros y grabaciones de nuestra comunidad.</p>
                    </div>
                    <a className="ct-signal" href={shell.channel}><i aria-hidden="true" />Señal 24/7<CtIcon name="arrow" /></a>
                </header>
                <Feedback />
                <form ref={form} className="ct-search" action={shell.catalog} role="search" aria-label="Buscar en el catálogo">
                    <input type="hidden" name="filtro" value={filter} />
                    <div className="ct-search__row">
                        <SearchBox defaultValue={filters.q || ""} suggestUrl={`${shell.catalog.replace(/\/$/, "")}/sugerencias`} />
                        <button type="submit" className="ct-search__submit" aria-label="Buscar"><CtIcon name="search" /><span>Buscar</span></button>
                        <button type="button" className={`ct-search__filters-toggle ${filtersOpen ? "is-open" : ""}`} aria-expanded={filtersOpen} aria-controls="ct-filters" onClick={() => setFiltersOpen((o) => !o)}>
                            <CtIcon name="filter" /><span>Filtros</span>{applied > 0 && <b aria-label={`${applied} activos`}>{applied}</b>}
                        </button>
                    </div>
                    <div id="ct-filters" className="ct-search__filters" hidden={!filtersOpen}>
                        {[["facultad_id", "Facultad", faculties, "Todas las facultades"],
                            ...(facultyCareers.length ? [["carrera_id", "Carrera", facultyCareers, "Todas las carreras"]] : []),
                            ["categoria_id", "Categoría", categories, "Todas las categorías"]].map(([name, label, options, placeholder]) => (
                            <label key={name} className="ct-select">
                                <span>{label}</span>
                                <select name={name} defaultValue={filters[name] || ""} onChange={() => {
                                    // Al cambiar de facultad, la carrera anterior ya no aplica.
                                    if (name === "facultad_id") { const career = form.current?.elements.namedItem("carrera_id"); if (career) career.value = ""; }
                                    form.current?.requestSubmit();
                                }}>
                                    <option value="">{placeholder}</option>
                                    {options.map((o) => <option key={o.id} value={o.id}>{o.nombre}</option>)}
                                </select>
                            </label>
                        ))}
                        {(applied > 0 || filters.q) && <a className="ct-clear" href={`${shell.catalog}?${new URLSearchParams({ filtro: filter })}`}>Limpiar filtros</a>}
                    </div>
                </form>
                <div className="ct-toolbar">
                    <div className="ct-toolbar__title">
                        <h2>Eventos y transmisiones</h2>
                        <p>{pagination.total} {pagination.total === 1 ? "resultado" : "resultados"}{filters.q ? <> para «{filters.q}»</> : null}{isAdmin && <span className="uebtv-catalog-sort-note"> · Más vistos primero</span>}</p>
                    </div>
                    <nav className="ct-tabs" aria-label="Filtrar por estado">
                        {Object.entries(tabs).map(([key, label]) => (
                            <a key={key} href={link(key)} className={filter === key ? "is-active" : ""} aria-current={filter === key ? "page" : undefined}>
                                {key === "en_vivo" && <i aria-hidden="true" />}{label}
                            </a>
                        ))}
                    </nav>
                </div>
                <section className="ct-grid" aria-label="Resultados">
                    {events.length ? events.map((e) => <Card key={e.id} event={e} />) : (
                        <div className="ct-empty">
                            <CtIcon name="search" />
                            <p><strong>No encontramos eventos.</strong> Prueba otro título o cambia los filtros.</p>
                        </div>
                    )}
                </section>
                <Pagination pagination={pagination} />
                {filter !== "proximos" && (
                    <section className="ct-upcoming" aria-labelledby="ct-upcoming-title">
                        <div className="ct-section-head">
                            <h2 id="ct-upcoming-title">Próximos eventos</h2>
                            {upcoming.length > 0 && <a href={link("proximos")}>Ver todos</a>}
                        </div>
                        {upcoming.length ? (
                            <ul className="ct-upcoming__list">
                                {upcoming.map((e) => (
                                    <li key={e.id}>
                                        <a href={e.url}>
                                            <CtIcon name="calendar" />
                                            <span><strong>{e.title}</strong><small>{[e.date, e.category].filter(Boolean).join(" · ")}</small></span>
                                        </a>
                                    </li>
                                ))}
                            </ul>
                        ) : <p className="ct-upcoming__empty">No hay próximos eventos{applied || filters.q ? " con estos filtros" : " anunciados por ahora"}.</p>}
                    </section>
                )}
            </div>
        </div>
    );
}
