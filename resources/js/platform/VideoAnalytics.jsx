import React, { useEffect, useMemo, useRef, useState } from "react";
import { createPortal } from "react-dom";

const number = new Intl.NumberFormat("es-EC");

function TrendChart({ points }) {
    const [activeIndex, setActiveIndex] = useState(null);
    const width = 900, height = 270, left = 46, right = 18, top = 22, bottom = 42;
    const maxValue = Math.max(0, ...points.map((point) => point.views));
    const max = maxValue <= 3 ? Math.max(1, maxValue) : Math.ceil(maxValue / 5) * 5;
    const coords = points.map((point, index) => ({
        x: left + (points.length < 2 ? 0 : index * (width - left - right) / (points.length - 1)),
        y: top + (height - top - bottom) * (1 - point.views / max),
        ...point,
    }));
    const line = coords.map((point, index) => `${index ? "L" : "M"}${point.x},${point.y}`).join(" ");
    const area = `${line} L${coords.at(-1)?.x ?? left},${height - bottom} L${coords[0]?.x ?? left},${height - bottom} Z`;
    const labelIndexes = [...new Set([0, Math.floor((coords.length - 1) / 2), coords.length - 1])];
    const yTicks = max === 1 ? [1, 0] : [...new Set([max, max / 2, 0])];
    const active = activeIndex === null ? null : coords[activeIndex];

    return <div className="video-analytics-chart-wrap">
        <div className={`video-analytics-chart-summary${active ? " is-selected" : ""}`} aria-live="polite"><strong>{active ? number.format(active.views) : number.format(points.reduce((sum, point) => sum + point.views, 0))}</strong><span>{active ? `visitas · ${active.label}` : "visitas registradas en el período"}</span><i>{active ? "DÍA SELECCIONADO" : "TOTAL DEL PERÍODO"}</i></div>
        <svg className="video-analytics-chart" viewBox={`0 0 ${width} ${height}`} role="img" aria-label="Curva de visualizaciones por día">
            <defs><linearGradient id="video-analytics-area" x1="0" x2="0" y1="0" y2="1"><stop offset="0%" stopColor="#2878c4" stopOpacity=".19" /><stop offset="100%" stopColor="#2878c4" stopOpacity=".015" /></linearGradient></defs>
            {yTicks.map((value) => {
                const y = top + (height - top - bottom) * (1 - value / max);
                return <g key={value}><line x1={left} x2={width - right} y1={y} y2={y} /><text x={left - 10} y={y + 4} textAnchor="end">{number.format(value)}</text></g>;
            })}
            <path className="video-analytics-chart__area" d={area} />
            <path className="video-analytics-chart__line" d={line} />
            {active && <line className="video-analytics-chart__guide" x1={active.x} x2={active.x} y1={top} y2={height - bottom} />}
            {coords.map((point, index) => <circle key={point.date} className={activeIndex === index ? "is-active" : ""} cx={point.x} cy={point.y} r={activeIndex === index ? "5" : "3.2"} tabIndex="0" role="button" aria-label={`${point.label}: ${number.format(point.views)} visitas`} onFocus={() => setActiveIndex(index)} onBlur={() => setActiveIndex(null)} onMouseEnter={() => setActiveIndex(index)} onMouseLeave={() => setActiveIndex(null)} />)}
            {labelIndexes.map((index) => <text className="video-analytics-chart__date" key={index} x={coords[index]?.x ?? left} y={height - 8} textAnchor={index === 0 ? "start" : index === coords.length - 1 ? "end" : "middle"}>{coords[index]?.label}</text>)}
        </svg>
    </div>;
}

function Ranking({ title, rows, empty }) {
    return <section className="video-analytics-ranking">
        <h3>{title}</h3>
        {rows?.length ? <ol>{rows.map((row, index) => <li key={row.id}>
            <span className="video-analytics-ranking__position">{index + 1}</span>
            <span className="video-analytics-ranking__title"><a href={row.url}>{row.title}</a><small>{row.type}</small></span>
            <strong>{number.format(row.views)}</strong>
        </li>)}</ol> : <p className="video-analytics-muted">{empty}</p>}
    </section>;
}

export default function VideoAnalytics({ url, title }) {
    const [days, setDays] = useState(30), [data, setData] = useState(null), [loading, setLoading] = useState(false), [error, setError] = useState(""), [open, setOpen] = useState(false);
    const trigger = useRef(null), close = useRef(null);
    useEffect(() => {
        if (!open) return undefined;
        const controller = new AbortController();
        setLoading(true); setError("");
        fetch(`${url}?days=${days}`, { signal: controller.signal, headers: { Accept: "application/json" }, credentials: "same-origin" })
            .then(async (response) => { if (!response.ok) throw new Error("No se pudieron cargar las estadísticas."); return response.json(); })
            .then(setData)
            .catch((reason) => { if (reason.name !== "AbortError") setError(reason.message); })
            .finally(() => { if (!controller.signal.aborted) setLoading(false); });
        return () => controller.abort();
    }, [url, days, open]);
    useEffect(() => {
        if (!open) return undefined;
        const previousOverflow = document.body.style.overflow;
        document.body.style.overflow = "hidden";
        close.current?.focus();
        const onKeyDown = (event) => { if (event.key === "Escape") setOpen(false); };
        document.addEventListener("keydown", onKeyDown);
        return () => { document.body.style.overflow = previousOverflow; document.removeEventListener("keydown", onKeyDown); trigger.current?.focus(); };
    }, [open]);
    const chart = useMemo(() => data?.daily ?? [], [data]);
    const growth = data?.current?.growthPercent;

    return <>
        <button ref={trigger} type="button" className="video-analytics-trigger" onClick={() => setOpen(true)} aria-haspopup="dialog" aria-expanded={open}>
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 19V5m0 14h17M8 15l4-4 3 2 5-6" /><circle cx="8" cy="15" r="1" /><circle cx="12" cy="11" r="1" /><circle cx="15" cy="13" r="1" /><circle cx="20" cy="7" r="1" /></svg>
            Ver estadísticas
        </button>
        {open && createPortal(<div className="video-analytics-backdrop" onMouseDown={(event) => { if (event.target === event.currentTarget) setOpen(false); }}>
        <section className="video-analytics" role="dialog" aria-modal="true" aria-label={`Estadísticas de ${title}`}>
        <header className="video-analytics__header">
            <div className="video-analytics__heading"><span className="video-analytics__mark" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 19V5m0 14h16M7 15l4-4 3 2 5-7" /></svg></span><div><p className="studio-eyebrow">ANÁLISIS DE AUDIENCIA · ADMINISTRACIÓN</p><h2>Estadísticas de reproducción</h2><p><span>{title}</span><i />Visitas únicas por navegador y día</p></div></div>
            <div className="video-analytics__controls"><label>Período<select value={days} onChange={(event) => setDays(Number(event.target.value))}><option value={7}>7 días</option><option value={30}>30 días</option><option value={90}>90 días</option></select></label><button ref={close} type="button" className="video-analytics__close" onClick={() => setOpen(false)} aria-label="Cerrar estadísticas">×</button></div>
        </header>
        {error ? <p className="video-analytics-message is-error" role="alert">{error}</p> : loading && !data ? <p className="video-analytics-message">Cargando estadísticas…</p> : <>
            <div className="video-analytics-metrics">
                <article className="is-primary"><span>Visitas del período</span><strong>{number.format(data?.current.views ?? 0)}</strong><small>Últimos {days} días</small></article>
                <article><span>Visitas acumuladas</span><strong>{number.format(data?.current.allTime ?? 0)}</strong><small>Desde el inicio del registro</small></article>
                <article><span>Día con más visitas</span><strong>{data?.current.peakDay ? number.format(data.current.peakDay.views) : "—"}</strong><small>{data?.current.peakDay?.label ?? "Sin visitas en el período"}</small></article>
                <article><span>Variación del período</span><strong className={growth > 0 ? "is-up" : growth < 0 ? "is-down" : ""}>{growth === null ? "Nuevo" : `${growth > 0 ? "+" : ""}${number.format(growth)}%`}</strong><small>{number.format(data?.current.previousViews ?? 0)} en el período anterior</small></article>
            </div>
            <div className="video-analytics-chart-card">
                <div className="video-analytics-section-title"><div><h3>Curva de crecimiento</h3><p>Visitas registradas por día para este video.</p></div><span><i /> Visitas</span></div>
                <TrendChart points={chart} />
            </div>
            <div className="video-analytics-rankings">
                <Ranking title="Más vistos en el catálogo" rows={data?.mostViewed} empty="Aún no hay videos con visitas registradas." />
                <Ranking title="Menos vistos en el catálogo" rows={data?.leastViewed} empty="Aún no hay videos con visitas registradas." />
            </div>
            <p className="video-analytics-footnote"><span aria-hidden="true">ⓘ</span> Un navegador cuenta una vez por video al día. La visita se registra cuando inicia la reproducción; en vivo, al cargar la señal.</p>
        </>}
        </section>
        </div>, document.body)}
    </>;
}

export function VideoAnalyticsInline({ url, title }) {
    const [days, setDays] = useState(30);
    const [data, setData] = useState(null);
    const [error, setError] = useState("");
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        const controller = new AbortController();
        setLoading(true);
        setError("");
        fetch(`${url}?days=${days}`, { signal: controller.signal, headers: { Accept: "application/json" }, credentials: "same-origin" })
            .then(async (response) => {
                if (!response.ok) throw new Error("No se pudieron cargar las estadísticas.");
                return response.json();
            })
            .then(setData)
            .catch((reason) => { if (reason.name !== "AbortError") setError(reason.message); })
            .finally(() => { if (!controller.signal.aborted) setLoading(false); });
        return () => controller.abort();
    }, [url, days]);

    return <section className="video-analytics-inline" aria-label={`Estadísticas de ${title}`}>
        <div className="video-analytics-inline__heading">
            <div><p className="studio-eyebrow">SOLO ADMINISTRACIÓN</p><h2>Estadísticas del video</h2><p>Visitas únicas por navegador y día · {title}</p></div>
            <label>Período <select value={days} onChange={(event) => setDays(Number(event.target.value))}>
                <option value={7}>7 días</option><option value={30}>30 días</option><option value={90}>90 días</option>
            </select></label>
        </div>
        {error && <p className="video-analytics-message is-error" role="alert">{error}</p>}
        {loading && !data && <p className="video-analytics-message" role="status">Cargando estadísticas…</p>}
        {data && <>
            <div className="video-analytics-metrics">
                <article className="is-primary"><span>Visitas del período</span><strong>{number.format(data.current.views)}</strong><small>Últimos {days} días</small></article>
                <article><span>Visitas acumuladas</span><strong>{number.format(data.current.allTime)}</strong><small>Desde el inicio del registro</small></article>
                <article><span>Día con más visitas</span><strong>{data.current.peakDay ? number.format(data.current.peakDay.views) : "—"}</strong><small>{data.current.peakDay?.label ?? "Sin visitas en el período"}</small></article>
                <article><span>Variación</span><strong>{data.current.growthPercent === null ? "Nuevo" : `${data.current.growthPercent > 0 ? "+" : ""}${number.format(data.current.growthPercent)}%`}</strong><small>Contra los {days} días anteriores</small></article>
            </div>
            <div className="video-analytics-chart-card"><div className="video-analytics-section-title"><div><h3>Curva de crecimiento</h3><p>Visualizaciones registradas por día.</p></div></div><TrendChart points={data.daily} /></div>
            <div className="video-analytics-rankings">
                <Ranking title="Más vistos" rows={data.mostViewed} empty="Aún no hay visitas registradas." />
                <Ranking title="Menos vistos" rows={data.leastViewed} empty="Aún no hay visitas registradas." />
            </div>
            <p className="video-analytics-footnote">La visita se registra al comenzar la reproducción; en vivo, al cargar la señal.</p>
        </>}
    </section>;
}
