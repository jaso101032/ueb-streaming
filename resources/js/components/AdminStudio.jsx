import React from "react";
import { liveDuration, useNow } from "../platform/shared";

/*
 * Resumen del panel: lo útil primero. Qué está al aire ahora, qué viene,
 * qué se está procesando o falló, cuatro números y los últimos eventos.
 */
const STATUS = { en_vivo: ["En vivo", "live"], programado: ["Programado", "info"], finalizado: ["Finalizado", "ok"], cancelado: ["Cancelado", "muted"] };

function DIcon({ name }) {
    const p = {
        calendar: <><rect x="3.5" y="5" width="17" height="15" rx="2.5" /><path d="M8 3v4m8-4v4M3.5 10h17" /></>,
        signal: <><circle cx="12" cy="12" r="2.2" /><path d="M7.8 7.8a6 6 0 0 0 0 8.4m8.4-8.4a6 6 0 0 1 0 8.4M5 5a10 10 0 0 0 0 14m14-14a10 10 0 0 1 0 14" /></>,
        eye: <><path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12Z" /><circle cx="12" cy="12" r="3" /></>,
        users: <><circle cx="9" cy="8" r="3.5" /><path d="M2.5 20a6.5 6.5 0 0 1 13 0M16 4.5a3.5 3.5 0 0 1 0 7M18.5 20a6.5 6.5 0 0 0-3-5.5" /></>,
        plus: <path d="M12 5v14M5 12h14" />,
        clock: <><circle cx="12" cy="12" r="8.5" /><path d="M12 7.5V12l3 2" /></>,
        megaphone: <path d="M4 10v4h3l7 4V6l-7 4H4Zm13-1.5a4 4 0 0 1 0 7" />,
        tv: <><rect x="3" y="6" width="18" height="12" rx="2" /><path d="M8 3l4 3 4-3" /></>,
        pause: <path d="M9 6v12m6-12v12" />,
        check: <path d="m5 12 4.5 4.5L19 7" />,
        alert: <><path d="M12 3 2.5 20h19z" /><path d="M12 10v4m0 3v.1" /></>,
        spin: <path d="M20 11a8 8 0 1 0-2.3 5.7M20 5v6h-6" />,
        arrow: <path d="M5 12h14m-6-6 6 6-6 6" />,
        out: <path d="M7 17 17 7M9 7h8v8" />,
        disk: <><ellipse cx="12" cy="6" rx="8" ry="3" /><path d="M4 6v12c0 1.7 3.6 3 8 3s8-1.3 8-3V6M4 12c0 1.7 3.6 3 8 3s8-1.3 8-3" /></>,
    };
    return <svg className="ad-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">{p[name]}</svg>;
}

function OnAir({ onAir, links, now }) {
    const icon = { live: null, scheduled: "tv", ad: "megaphone", idle: "pause" }[onAir.mode];
    const label = { live: "EN VIVO", scheduled: "CANAL 24/7", ad: "PUBLICIDAD", idle: "SIN EMISIÓN" }[onAir.mode];
    const detail = onAir.mode === "live" && onAir.since ? `En directo hace ${liveDuration(onAir.since, now)}`
        : onAir.mode === "scheduled" && onAir.endsAt ? `Termina a las ${new Date(onAir.endsAt).toLocaleTimeString("es-EC", { hour: "2-digit", minute: "2-digit" })}`
        : onAir.detail;
    return (
        <section className={`ad-card ad-onair is-${onAir.mode}`} aria-labelledby="ad-onair-title">
            <p className="ad-card__eyebrow">Ahora en la señal</p>
            <div className="ad-onair__body">
                <span className={`ad-onair__badge is-${onAir.mode}`}>{onAir.mode === "live" ? <i aria-hidden="true" /> : <DIcon name={icon} />}{label}</span>
                <h2 id="ad-onair-title"><a href={onAir.url}>{onAir.title}</a></h2>
                <p>{detail}</p>
            </div>
            <div className="ad-onair__actions">
                <a className="ad-btn is-primary" href={links.streaming}><DIcon name="signal" />Emitir con OBS</a>
                <a className="ad-btn" href={links.channel} target="_blank" rel="noopener"><DIcon name="out" />Ver la señal</a>
            </div>
        </section>
    );
}

export function humanBytes(bytes) {
    const value = Math.max(0, Number(bytes) || 0);
    if (value >= 1024 ** 4) return `${(value / 1024 ** 4).toLocaleString("es-EC", { maximumFractionDigits: 1 })} TB`;
    if (value >= 1024 ** 3) return `${(value / 1024 ** 3).toLocaleString("es-EC", { maximumFractionDigits: 1 })} GB`;
    if (value >= 1024 ** 2) return `${Math.round(value / 1024 ** 2).toLocaleString("es-EC")} MB`;
    return `${Math.round(value / 1024).toLocaleString("es-EC")} KB`;
}

const LEVEL_TEXT = {
    ok: "Hay espacio suficiente para subir videos y grabar directos.",
    low: "Queda poco espacio. Conviene liberar algo antes de subir videos largos.",
    critical: "Sin espacio: no se pueden subir videos y las grabaciones en vivo podrían cortarse.",
};

/** Cuánto espacio queda y en qué se gasta, con acceso directo a liberar espacio. */
function Storage({ storage }) {
    if (!storage?.available) return null;
    const media = storage.breakdown || [];
    const total = Math.max(storage.totalBytes, 1);
    const level = storage.level || "ok";
    return (
        <section className={`ad-card ad-storage is-${level}`} aria-labelledby="ad-storage-title">
            <div className="ad-card__head">
                <h2 id="ad-storage-title" className="ad-card__eyebrow">Almacenamiento {level !== "ok" && <b className={`ad-pill ${level === "critical" ? "is-bad" : "is-warn"}`}>{level === "critical" ? "Sin espacio" : "Poco espacio"}</b>}</h2>
                <a href={storage.freeUrl}>Liberar espacio <DIcon name="arrow" /></a>
            </div>
            <div className="ad-storage__summary">
                <span className="ad-metric__icon"><DIcon name="disk" /></span>
                <p><strong>{humanBytes(storage.freeBytes)}</strong> libres de {humanBytes(storage.totalBytes)}<small>{LEVEL_TEXT[level]}</small></p>
            </div>
            <div className="ad-storage__bar" role="img" aria-label={`Usado ${storage.usedPercent} %: ${media.map((m) => `${m.label} ${humanBytes(m.bytes)}`).join(", ")}`}>
                {media.map((m) => m.bytes > 0 && <span key={m.key} className={`is-${m.key}`} style={{ width: `${(m.bytes / total) * 100}%` }} />)}
                {storage.basis === "filesystem" && <span className="is-system" style={{ width: `${Math.max(0, storage.usedBytes - storage.mediaUsedBytes) / total * 100}%` }} />}
            </div>
            <ul className="ad-storage__legend">
                {media.map((m) => <li key={m.key}><i className={`is-${m.key}`} aria-hidden="true" />{m.label}<b>{humanBytes(m.bytes)}</b></li>)}
                {storage.basis === "filesystem" && <li><i className="is-system" aria-hidden="true" />Sistema y otros programas<b>{humanBytes(Math.max(0, storage.usedBytes - storage.mediaUsedBytes))}</b></li>}
                <li><i className="is-free" aria-hidden="true" />Libre<b>{humanBytes(storage.freeBytes)}</b></li>
            </ul>
        </section>
    );
}

function guideTime(iso) {
    const date = new Date(iso);
    if (Number.isNaN(date.getTime())) return "";
    const today = new Date(), tomorrow = new Date(); tomorrow.setDate(today.getDate() + 1);
    const t = date.toLocaleTimeString("es-EC", { hour: "2-digit", minute: "2-digit" });
    if (date.toDateString() === today.toDateString()) return `Hoy · ${t}`;
    if (date.toDateString() === tomorrow.toDateString()) return `Mañana · ${t}`;
    return `${date.toLocaleDateString("es-EC", { weekday: "short", day: "numeric", month: "short" })} · ${t}`;
}

export default function AdminStudio({ name, onAir, upcoming = [], tasks = [], metrics = [], events = [], links = {}, storage = null }) {
    const now = useNow(30000);
    const failed = tasks.filter((t) => t.state === "bad").length;
    const hour = new Date().getHours();
    const greeting = hour < 12 ? "Buenos días" : hour < 19 ? "Buenas tardes" : "Buenas noches";
    return (
        <div className="studio-page ad">
            <header className="ad-head">
                <div>
                    <p className="ad-eyebrow">Resumen</p>
                    <h1>{greeting}, {String(name || "").split(" ")[0]}</h1>
                </div>
                <nav className="ad-quick" aria-label="Acciones rápidas">
                    <a className="ad-btn" href={links.schedule}><DIcon name="clock" />Programar video</a>
                    <a className="ad-btn" href={links.ads}><DIcon name="megaphone" />Subir publicidad</a>
                </nav>
            </header>

            <div className="ad-top">
                {onAir && <OnAir onAir={onAir} links={links} now={now} />}
                <section className="ad-card ad-next" aria-labelledby="ad-next-title">
                    <div className="ad-card__head"><h2 id="ad-next-title" className="ad-card__eyebrow">A continuación</h2><a href={links.schedule}>Parrilla 24/7 <DIcon name="arrow" /></a></div>
                    {upcoming.length ? <ul className="ad-next__list">
                        {upcoming.map((item) => <li key={`${item.kind}-${item.id}`}>
                            <a href={item.url}>
                                <span className={`ad-thumb ${item.image ? "has-image" : ""}`} style={item.image ? { backgroundImage: `url(${JSON.stringify(item.image)})` } : undefined} aria-hidden="true">{!item.image && <img src="/images/brand/ueb-wordmark.png" alt="" />}</span>
                                <span className="ad-next__text"><strong>{item.title}</strong><small>{guideTime(item.starts_at)} · {item.kind === "live" ? "Directo" : "Canal 24/7"}</small></span>
                            </a>
                        </li>)}
                    </ul> : <div className="ad-empty"><DIcon name="calendar" /><p>No hay emisiones anunciadas.</p><a className="ad-btn" href={links.schedule}>Programar un video</a></div>}
                </section>
            </div>

            <section className="ad-metrics" aria-label="Números de la plataforma">
                {metrics.map((m) => <div className="ad-metric" key={m.label}>
                    <span className="ad-metric__icon"><DIcon name={m.icon} /></span>
                    <span className="ad-metric__text"><strong>{new Intl.NumberFormat("es-EC").format(m.value)}</strong><span>{m.label}</span><small>{m.detail}</small></span>
                </div>)}
            </section>

            <Storage storage={storage} />

            <div className="ad-bottom">
                <section className="ad-card" aria-labelledby="ad-tasks-title">
                    <div className="ad-card__head">
                        <h2 id="ad-tasks-title" className="ad-card__eyebrow">Procesamiento {failed > 0 && <b className="ad-pill is-bad">{failed} con error</b>}</h2>
                        <a href={links.media}>Biblioteca <DIcon name="arrow" /></a>
                    </div>
                    {tasks.length ? <ul className="ad-tasks">
                        {tasks.map((t, i) => <li key={i} className={`is-${t.state}`}>
                            <a href={t.url}>
                                <span className="ad-tasks__icon"><DIcon name={t.state === "bad" ? "alert" : "spin"} /></span>
                                <span className="ad-tasks__text"><strong>{t.title}</strong><small>{t.what} · {t.state === "bad" ? "Falló, revisa el archivo" : "En proceso"}</small></span>
                            </a>
                        </li>)}
                    </ul> : <div className="ad-empty is-ok"><DIcon name="check" /><p><strong>Todo al día.</strong> No hay grabaciones, calidades ni subtítulos pendientes.</p></div>}
                </section>
                <section className="ad-card" aria-labelledby="ad-recent-title">
                    <div className="ad-card__head"><h2 id="ad-recent-title" className="ad-card__eyebrow">Eventos recientes</h2><a href={links.events}>Ver todos <DIcon name="arrow" /></a></div>
                    {events.length ? <ul className="ad-events">
                        {events.map((e) => {
                            const [text, tone] = STATUS[e.status] || [e.status, "muted"];
                            return <li key={e.id}><a href={e.url}>
                                <span className={`ad-thumb ${e.image ? "has-image" : ""}`} style={e.image ? { backgroundImage: `url(${JSON.stringify(e.image)})` } : undefined} aria-hidden="true">{!e.image && <img src="/images/brand/ueb-wordmark.png" alt="" />}</span>
                                <span className="ad-events__text"><strong>{e.title}</strong><small>{e.category} · {e.date}</small></span>
                                <span className={`ad-status is-${tone}`}>{text}</span>
                            </a></li>;
                        })}
                    </ul> : <div className="ad-empty"><DIcon name="calendar" /><p>Aún no hay eventos.</p><a className="ad-btn is-primary" href={links.create}>Crear un evento</a></div>}
                </section>
            </div>
        </div>
    );
}
