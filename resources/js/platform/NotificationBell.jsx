import React, { useEffect, useRef, useState } from "react";

function decodeKey(value) {
    const padded = `${value.replace(/-/g, "+").replace(/_/g, "/")}${"=".repeat((4 - value.length % 4) % 4)}`;
    return Uint8Array.from(atob(padded), (character) => character.charCodeAt(0));
}

/** "hace 5 min", "hace 2 h", "ayer", "12 nov." */
function relativeTime(iso) {
    const date = new Date(iso);
    const minutes = Math.round((Date.now() - date.getTime()) / 60000);
    if (Number.isNaN(minutes)) return "";
    if (minutes < 1) return "ahora";
    if (minutes < 60) return `hace ${minutes} min`;
    const hours = Math.round(minutes / 60);
    if (hours < 24) return `hace ${hours} h`;
    if (hours < 48) return "ayer";
    return date.toLocaleDateString("es-EC", { day: "numeric", month: "short" });
}

const NIcon = ({ d, children }) => <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.9" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">{d ? <path d={d} /> : children}</svg>;

export default function NotificationBell({ urls, csrf }) {
    const [open, setOpen] = useState(false);
    const [view, setView] = useState("list");
    const [data, setData] = useState({ items: [], unread: 0, preferences: { live: true, videos: true }, pushKey: null, pushEndpoints: [] });
    const [pushActive, setPushActive] = useState(false);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState("");
    const panelRef = useRef(null);
    const supported = typeof window !== "undefined" && window.isSecureContext && "serviceWorker" in navigator && "PushManager" in window && "Notification" in window;

    async function load() {
        const response = await fetch(urls.index, { credentials: "same-origin", headers: { Accept: "application/json" } });
        if (response.ok) { const payload = await response.json(); setData(payload); return payload; }
        return null;
    }

    async function mutation(url, method, payload) {
        const response = await fetch(url, {
            method,
            credentials: "same-origin",
            headers: { "Content-Type": "application/json", Accept: "application/json", "X-CSRF-TOKEN": csrf },
            body: JSON.stringify(payload),
        });
        if (!response.ok) throw new Error(response.status === 419 ? "La sesión caducó. Recarga la página." : "No se pudo guardar. Inténtalo de nuevo.");
    }

    useEffect(() => {
        load().catch(() => {});
        const timer = window.setInterval(() => { if (!document.hidden) load().catch(() => {}); }, 60000);
        return () => window.clearInterval(timer);
    }, [urls.index]);

    useEffect(() => {
        if (!open) return undefined;
        load().then((payload) => {
            if (supported && payload) navigator.serviceWorker.getRegistration("/").then((registration) => registration?.pushManager.getSubscription())
                .then((subscription) => setPushActive(Boolean(subscription && payload.pushEndpoints.includes(subscription.endpoint)))).catch(() => {});
        }).catch(() => setError("No se pudieron cargar los avisos."));
        setView("list");
        const close = (event) => { if (!panelRef.current?.contains(event.target)) setOpen(false); };
        const escape = (event) => { if (event.key === "Escape") setOpen(false); };
        document.addEventListener("pointerdown", close);
        document.addEventListener("keydown", escape);
        return () => { document.removeEventListener("pointerdown", close); document.removeEventListener("keydown", escape); };
    }, [open, supported]);

    async function changePreference(key) {
        const preferences = { ...data.preferences, [key]: !data.preferences[key] };
        const previous = data.preferences;
        setBusy(true); setError("");
        setData((current) => ({ ...current, preferences }));
        try {
            await mutation(urls.preferences, "PUT", preferences);
        } catch (failure) { setData((current) => ({ ...current, preferences: previous })); setError(failure.message); }
        finally { setBusy(false); }
    }

    async function togglePush() {
        if (!supported || !data.pushKey) return;
        setBusy(true); setError("");
        try {
            if (pushActive) {
                const registration = await navigator.serviceWorker.getRegistration("/");
                const subscription = await registration?.pushManager.getSubscription();
                if (subscription) {
                    await mutation(urls.push, "DELETE", { endpoint: subscription.endpoint });
                    await subscription.unsubscribe();
                }
                setPushActive(false);
            } else {
                // Browser permission is requested only from this explicit click.
                const permission = await Notification.requestPermission();
                if (permission !== "granted") throw new Error("Activa los permisos de notificación en tu navegador para recibir avisos.");
                const registration = await navigator.serviceWorker.register("/uebtv-push-sw.js", { scope: "/" });
                let subscription = await registration.pushManager.getSubscription();
                if (!subscription) subscription = await registration.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: decodeKey(data.pushKey) });
                await mutation(urls.push, "POST", subscription.toJSON());
                setPushActive(true);
            }
        } catch (failure) { setError(failure.message || "No se pudo cambiar el estado de las notificaciones."); }
        finally { setBusy(false); }
    }

    async function read(item) {
        if (!item.read_at) {
            try { await mutation(`${urls.readBase}/${item.id}/leer`, "PATCH", {}); }
            catch (_) { /* The destination remains usable even if marking read fails. */ }
        }
        window.location.href = item.data.url;
    }

    return <div className="studio-notifications" ref={panelRef}>
        <button type="button" className={`studio-notifications__toggle ${open ? "is-open" : ""}`} aria-label={`Notificaciones${data.unread ? `, ${data.unread} sin leer` : ""}`} aria-expanded={open} aria-controls="studio-notifications-panel" onClick={() => setOpen((value) => !value)}>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4" /></svg>
            {data.unread > 0 && <span className="studio-notifications__count">{data.unread > 9 ? "9+" : data.unread}</span>}
        </button>
        {open && <section id="studio-notifications-panel" className="studio-notifications__panel nt" aria-label="Centro de notificaciones">
            <div className="nt-head">
                {view === "settings"
                    ? <button type="button" className="nt-icon-btn" onClick={() => setView("list")} aria-label="Volver a las notificaciones"><NIcon d="M15 5l-7 7 7 7" /></button>
                    : null}
                <h2>{view === "settings" ? "Ajustes" : "Notificaciones"}</h2>
                {view === "list" && <button type="button" className="nt-icon-btn" onClick={() => setView("settings")} aria-label="Ajustes de notificaciones" title="Ajustes">
                    <NIcon><circle cx="12" cy="12" r="3" /><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1Z" /></NIcon>
                </button>}
                <button type="button" className="nt-icon-btn" onClick={() => setOpen(false)} aria-label="Cerrar notificaciones"><NIcon d="M6 6l12 12M18 6 6 18" /></button>
            </div>
            {error && <p className="nt-error" role="alert">{error}</p>}
            {view === "list" ? <div className="nt-list">
                {data.items.length ? data.items.map((item) => {
                    const live = item.data.kind === "live";
                    return <button key={item.id} type="button" className={`nt-item ${item.read_at ? "" : "is-unread"}`} onClick={() => read(item)}>
                        <span className="nt-dot" aria-hidden="true" />
                        <span className={`nt-thumb ${item.image ? "has-image" : ""}`} style={item.image ? { backgroundImage: `url(${JSON.stringify(item.image)})` } : undefined} aria-hidden="true">
                            {!item.image && <img src="/images/brand/ueb-wordmark.png" alt="" />}
                            <span className={`nt-badge ${live ? "is-live" : ""}`}>{live ? "EN VIVO" : "NUEVO"}</span>
                        </span>
                        <span className="nt-text">
                            <strong>{item.data.title}</strong>
                            {item.data.body && <span>{item.data.body}</span>}
                            <time dateTime={item.created_at} title={new Date(item.created_at).toLocaleString("es-EC", { dateStyle: "medium", timeStyle: "short" })}>{relativeTime(item.created_at)}</time>
                        </span>
                        {!item.read_at && <span className="ct-sr">sin leer</span>}
                    </button>;
                }) : <div className="nt-empty">
                    <span><NIcon d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4" /></span>
                    <strong>Estás al día</strong>
                    <p>Aquí aparecerán los directos y los videos nuevos.</p>
                </div>}
            </div> : <div className="nt-settings">
                <p className="nt-settings__title">Avísame cuando haya</p>
                <label className="nt-switch"><span><strong>Directos</strong><small>Cuando empiece una transmisión en vivo.</small></span><input type="checkbox" checked={data.preferences.live} disabled={busy} onChange={() => changePreference("live")} /></label>
                <label className="nt-switch"><span><strong>Videos nuevos</strong><small>Cuando se publique una grabación o programa.</small></span><input type="checkbox" checked={data.preferences.videos} disabled={busy} onChange={() => changePreference("videos")} /></label>
                {supported && data.pushKey
                    ? <button type="button" className="nt-push" disabled={busy} onClick={togglePush}>{pushActive ? "Desactivar avisos del navegador" : "Activar avisos del navegador"}</button>
                    : <p className="nt-hint">Los avisos del navegador no están disponibles en esta conexión. La campana sigue funcionando.</p>}
            </div>}
        </section>}
    </div>;
}
