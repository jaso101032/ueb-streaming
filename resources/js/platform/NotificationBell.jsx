import React, { useEffect, useRef, useState } from "react";

function decodeKey(value) {
    const padded = `${value.replace(/-/g, "+").replace(/_/g, "/")}${"=".repeat((4 - value.length % 4) % 4)}`;
    return Uint8Array.from(atob(padded), (character) => character.charCodeAt(0));
}

export default function NotificationBell({ urls, csrf }) {
    const [open, setOpen] = useState(false);
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
        {open && <section id="studio-notifications-panel" className="studio-notifications__panel" aria-label="Centro de notificaciones">
            <div className="studio-notifications__heading"><div><small>UEBTV</small><h2>Notificaciones</h2></div><button type="button" onClick={() => setOpen(false)} aria-label="Cerrar notificaciones">×</button></div>
            {error && <p className="studio-notifications__error" role="alert">{error}</p>}
            <div className="studio-notifications__list">
                {data.items.length ? data.items.map((item) => <button key={item.id} type="button" className={`studio-notifications__item ${item.read_at ? "" : "is-unread"}`} onClick={() => read(item)}>
                    <span className="studio-notifications__item-type">{item.data.kind === "live" ? "● EN VIVO" : "▶ VIDEO NUEVO"}</span>
                    <strong>{item.data.title}</strong><span>{item.data.body}</span>
                    <time dateTime={item.created_at}>{new Date(item.created_at).toLocaleString("es-EC", { dateStyle: "medium", timeStyle: "short" })}</time>
                </button>) : <p className="studio-notifications__empty">Todavía no hay novedades. Aquí aparecerán los directos y videos nuevos.</p>}
            </div>
            <div className="studio-notifications__settings">
                <h3>Qué quieres recibir</h3>
                <label><input type="checkbox" checked={data.preferences.live} disabled={busy} onChange={() => changePreference("live")} /> Directos</label>
                <label><input type="checkbox" checked={data.preferences.videos} disabled={busy} onChange={() => changePreference("videos")} /> Videos nuevos</label>
                {supported && data.pushKey ? <button type="button" className="studio-notifications__push" disabled={busy} onClick={togglePush}>{pushActive ? "Desactivar avisos del navegador" : "Activar avisos del navegador"}</button> : <p className="studio-notifications__hint">Los avisos del navegador no están disponibles aquí. La campana seguirá funcionando.</p>}
                <p className="studio-notifications__hint">Puedes cambiar estas opciones cuando quieras.</p>
            </div>
        </section>}
    </div>;
}
