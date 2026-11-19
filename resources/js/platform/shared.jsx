import React, { createContext, useContext, useState, useEffect, useLayoutEffect, useRef, useId } from "react";
import { createPortal } from "react-dom";
export const Context = createContext({});
export const useShell = () => useContext(Context);
export function Token({ method }) {
    const { csrf } = useShell();
    return (
        <>
            <input type="hidden" name="_token" value={csrf} />
            {method && <input type="hidden" name="_method" value={method} />}
        </>
    );
}
export function Action({
    url,
    children,
    method,
    confirm,
    className = "studio-button",
    ariaLabel,
}) {
    const [busy, setBusy] = useState(false);
    useEffect(() => {
        const reset = () => setBusy(false);
        window.addEventListener("pageshow", reset);
        return () => window.removeEventListener("pageshow", reset);
    }, []);
    return (
        <form
            method="POST"
            action={url}
            onSubmit={(e) => {
                if (confirm && !window.confirm(confirm)) {
                    e.preventDefault();
                    return;
                }
                setBusy(true);
            }}
        >
            <Token method={method} />
            <button className={className} aria-label={ariaLabel} disabled={busy}>
                {busy ? "Procesando…" : children}
            </button>
        </form>
    );
}
export function Feedback() {
    const { errors = [], success, status } = useShell();
    return (
        <>
            {errors.length > 0 && (
                <div className="uebtv-form-errors" role="alert">
                    {errors.map((e, i) => (
                        <p key={i}>{e}</p>
                    ))}
                </div>
            )}
            {(success || status) && (
                <p className="studio-alert" role="status">
                    {success ||
                        {
                            "profile-updated": "Perfil actualizado.",
                            "password-updated": "Contraseña actualizada.",
                            "verification-link-sent":
                                "Enlace de verificación enviado.",
                        }[status] ||
                        status}
                </p>
            )}
        </>
    );
}
export function Pagination({ pagination: p }) {
    return (
        p?.last > 1 && (
            <nav className="schedule-pagination" aria-label="Páginas">
                {p.previous && <a href={p.previous}>← Anterior</a>}
                <span>
                    Página {p.current} de {p.last}
                </span>
                {p.next && <a href={p.next}>Siguiente →</a>}
            </nav>
        )
    );
}
export const statusLabel = (e) =>
    e.scheduled
        ? {
              processing: "Preparando video",
              failed: "Video no disponible",
              upcoming: "Próximamente · Pregrabado",
              playing: "En emisión · Pregrabado",
              paused: "Pausado por directo",
              ended: "Pregrabado · Disponible",
              cancelled: "Cancelado",
          }[e.scheduled.state]
        : {
              en_vivo: "En vivo",
              programado: "Programado",
              finalizado: "Finalizado",
              cancelado: "Cancelado",
          }[e.status];
export function Heading({ eyebrow, title, children }) {
    return (
        <div className="studio-heading">
            <div>
                <p className="studio-eyebrow">{eyebrow}</p>
                <h1>
                    {title}
                    <span className="studio-heading-dot">.</span>
                </h1>
                {children && <p>{children}</p>}
            </div>
        </div>
    );
}
export function Field({ name, label, children, wide, ...props }) {
    return (
        <div
            className={`uebtv-form-field ${wide ? "uebtv-form-field--wide" : ""}`}
        >
            <label htmlFor={name}>{label}</label>
            {children || <input id={name} name={name} {...props} />}
        </div>
    );
}

const SUBTITLE_STATUS = {
    pending: "En cola: los subtítulos automáticos se generarán en cuanto el servidor esté libre.",
    processing: "Generando subtítulos automáticos a partir del audio…",
    no_speech: "No se detectó voz en este video, así que no se generaron subtítulos.",
    skipped: "Este video no tiene subtítulos. Puedes generarlos automáticamente.",
};

const SubIcon = ({ d }) => <svg className="subtitle-form__icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d={d} /></svg>;
const SUB_ICONS = {
    captions: "M4 6h16v12H4zM8 11h3m2 0h3M8 14h5",
    retry: "M20 11a8 8 0 1 0-2.3 5.7M20 5v6h-6",
    trash: "M4 7h16M10 11v6m4-6v6M6 7l1 12a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2l1-12M9 7V4h6v3",
};
/** Subtítulos automáticos (Whisper) de un video almacenado: estado, generar y quitar. */
export function SubtitleForm({ subtitles }) {
    if (!subtitles) return null;
    const { present, source, status, progress, error } = subtitles;
    const working = !present && (status === "pending" || status === "processing");
    let message = null;
    if (present) {
        message = source === "auto"
            ? "Subtítulos generados automáticamente a partir del audio del video."
            : "Subtítulos subidos por el equipo.";
    } else if (status === "failed") {
        message = error || "No se pudieron generar los subtítulos automáticos.";
    } else if (status) {
        message = SUBTITLE_STATUS[status] || null;
    }
    return (
        <div className="subtitle-form" data-subtitle-status={present ? "ready" : status || "none"}>
            {message && (
                <p className={`subtitle-form__status${status === "failed" && !present ? " is-error" : ""}`} role="status">
                    {present && <span className="subtitle-form__badge">{source === "auto" ? "Automáticos" : "Manuales"}</span>}
                    {message}
                </p>
            )}
            {working && (
                <div className="subtitle-form__progress">
                    <progress max="100" value={status === "processing" ? progress || 0 : undefined} aria-label="Avance de la transcripción" />
                    <small>{status === "processing" ? `${progress || 0}% · ` : ""}Actualiza la página para ver el avance.</small>
                </div>
            )}
            <div className="subtitle-form__actions">
                {subtitles.generateUrl && (
                    <Action url={subtitles.generateUrl} className="studio-button">
                        <SubIcon d={status === "failed" ? SUB_ICONS.retry : SUB_ICONS.captions} />
                        {status === "failed" ? "Reintentar subtítulos automáticos" : "Generar subtítulos automáticos"}
                    </Action>
                )}
                {present && subtitles.removeUrl && (
                    <Action url={subtitles.removeUrl} method="DELETE" className="studio-button studio-button-soft" confirm="¿Quitar los subtítulos de este video? No se volverán a generar solos.">
                        <SubIcon d={SUB_ICONS.trash} />Quitar subtítulos
                    </Action>
                )}
            </div>
        </div>
    );
}

/**
 * Copia texto al portapapeles. Usa la API moderna cuando la página es segura
 * (https o localhost) y, si no, el método clásico (por ejemplo al probar desde
 * el celular por http en la red local). Devuelve si se pudo copiar.
 */
export async function copyText(text) {
    try {
        if (window.isSecureContext && navigator.clipboard?.writeText) { await navigator.clipboard.writeText(text); return true; }
    } catch { /* se intenta el método clásico */ }
    const area = document.createElement("textarea");
    area.value = text; area.setAttribute("readonly", ""); area.style.cssText = "position:fixed;top:0;left:0;opacity:0;pointer-events:none";
    document.body.appendChild(area); area.select(); area.setSelectionRange(0, text.length);
    let ok = false;
    try { ok = document.execCommand("copy"); } catch { ok = false; }
    area.remove();
    return ok;
}

const SHARE_TARGETS = [
    { id: "whatsapp", label: "WhatsApp", href: (u, t) => `https://wa.me/?text=${encodeURIComponent(`${t} ${u}`)}`,
        icon: <path d="M5 19l1.2-3.6A7.5 7.5 0 1 1 9 18.3zM9.5 9.5c.3 2 2 3.8 4 4.3l1-1 1.7.8c-.2 1-1 1.6-2 1.5-2.8-.3-5.3-2.8-5.6-5.6-.1-1 .5-1.8 1.5-2l.8 1.7z" /> },
    { id: "facebook", label: "Facebook", href: (u) => `https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(u)}`,
        icon: <path d="M13.5 20v-7h2.4l.4-2.8h-2.8V8.5c0-.8.3-1.3 1.4-1.3h1.5V4.7a19 19 0 0 0-2.2-.1c-2.2 0-3.6 1.3-3.6 3.7v1.9H8.2V13h2.4v7" /> },
    { id: "x", label: "X", href: (u, t) => `https://twitter.com/intent/tweet?text=${encodeURIComponent(t)}&url=${encodeURIComponent(u)}`,
        icon: <path d="M5 5l14 14M19 5 5 19" /> },
    { id: "telegram", label: "Telegram", href: (u, t) => `https://t.me/share/url?url=${encodeURIComponent(u)}&text=${encodeURIComponent(t)}`,
        icon: <path d="M20 5 3.5 11.4l5 1.8 1.9 5.6 2.8-3.3 4.3 3.2zM8.5 13.2l8-5.4-6.1 7" /> },
    { id: "email", label: "Correo", href: (u, t) => `mailto:?subject=${encodeURIComponent(t)}&body=${encodeURIComponent(`${t}\n${u}`)}`,
        icon: <><rect x="3.5" y="5.5" width="17" height="13" rx="2" /><path d="m4 7 8 6 8-6" /></> },
];

/**
 * Botón "Compartir". En celulares con menú de compartir del sistema lo abre;
 * en el resto (computadoras, o páginas sin https) muestra un menú propio con
 * WhatsApp, Facebook, X, Telegram, correo y "Copiar enlace".
 */
export function ShareButton({ url, title, label = "Compartir", className = "wv-chip", icon }) {
    const [open, setOpen] = useState(false);
    const [copied, setCopied] = useState("");
    const [place, setPlace] = useState(null);
    const menu = useRef(null), button = useRef(null), first = useRef(null), id = useId();
    const shareUrl = url || window.location.href;
    useEffect(() => {
        if (!open) return undefined;
        first.current?.focus({ preventScroll: true });
        const outside = (event) => { if (!menu.current?.contains(event.target) && !button.current?.contains(event.target)) setOpen(false); };
        const key = (event) => { if (event.key === "Escape") { setOpen(false); button.current?.focus(); } };
        document.addEventListener("pointerdown", outside); document.addEventListener("keydown", key);
        return () => { document.removeEventListener("pointerdown", outside); document.removeEventListener("keydown", key); };
    }, [open]);
    // El menú se dibuja sobre la página (fuera de filas con desplazamiento que lo
    // recortarían) y se coloca bajo el botón, sin salirse de la pantalla.
    useLayoutEffect(() => {
        if (!open) return undefined;
        const update = () => {
            const rect = button.current?.getBoundingClientRect(); if (!rect) return;
            const width = Math.min(330, window.innerWidth - 32), height = menu.current?.offsetHeight || 230;
            const below = rect.bottom + 8 + height <= window.innerHeight || rect.top < height + 8;
            setPlace({ width, left: Math.max(16, Math.min(rect.left, window.innerWidth - width - 16)), top: below ? rect.bottom + 8 : rect.top - height - 8 });
        };
        update();
        window.addEventListener("resize", update); window.addEventListener("scroll", update, true);
        return () => { window.removeEventListener("resize", update); window.removeEventListener("scroll", update, true); };
    }, [open]);
    useEffect(() => { if (!copied) return undefined; const t = window.setTimeout(() => setCopied(""), 2500); return () => window.clearTimeout(t); }, [copied]);
    async function onClick() {
        const touch = window.matchMedia?.("(pointer: coarse)").matches;
        if (touch && navigator.share) {
            try { await navigator.share({ title, url: shareUrl }); return; }
            catch (error) { if (error?.name === "AbortError") return; }
        }
        setOpen((value) => !value);
    }
    async function copy() {
        setCopied(await copyText(shareUrl) ? "¡Enlace copiado!" : "No se pudo copiar. Mantén presionado el enlace para copiarlo.");
    }
    return (
        <span className="share">
            <button ref={button} type="button" className={className} onClick={onClick} aria-haspopup="dialog" aria-expanded={open} aria-controls={open ? id : undefined}>
                {icon}<span className="share__label">{label}</span>
            </button>
            {open && createPortal(<div ref={menu} id={id} className="share-layer share__menu" role="dialog" aria-label={`Compartir: ${title}`}
                style={place ? { top: place.top, left: place.left, width: place.width } : { visibility: "hidden" }}>
                <div className="share__head"><strong>Compartir</strong><button type="button" className="share__close" onClick={() => { setOpen(false); button.current?.focus(); }} aria-label="Cerrar">✕</button></div>
                <div className="share__targets">
                    {SHARE_TARGETS.map((target, index) => (
                        <a key={target.id} ref={index === 0 ? first : undefined} className={`share__target is-${target.id}`} href={target.href(shareUrl, title)}
                            target={target.id === "email" ? undefined : "_blank"} rel="noopener noreferrer" onClick={() => setOpen(false)}>
                            <span aria-hidden="true"><svg viewBox="0 0 24 24" focusable="false">{target.icon}</svg></span>{target.label}
                        </a>
                    ))}
                </div>
                <div className="share__copy">
                    <input type="text" readOnly value={shareUrl} aria-label="Enlace para compartir" onFocus={(event) => event.target.select()} />
                    <button type="button" onClick={copy}>Copiar</button>
                </div>
                <p className="share__status" role="status">{copied}</p>
            </div>, document.body)}
        </span>
    );
}

/** Tarjeta de la app: el zorrito con el QR para descargar UEBTV. */
export function AppCard({ titleId = "channel-app-title" }) {
    return (
        <section className="channel-app-card cv-app" aria-labelledby={titleId}>
            <div className="channel-app-art">
                <div className="channel-app-art-inner">
                    <img className="channel-app-mascot" src="/images/download/zorrito-qr-gafas.webp" alt="" width="960" height="800" />
                    <div className="channel-app-qr">
                        <img src="/images/download/uebtv-apk-qr-v2.png" alt="Código QR para descargar UEBTV" width="400" height="400" />
                    </div>
                </div>
            </div>
            <div className="channel-app-copy">
                <p className="channel-app-eyebrow">UEBTV EN TU PANTALLA</p>
                <h2 id={titleId}>Lleva la señal <span>contigo.</span></h2>
                <p>Escanea el QR desde otro dispositivo o descarga el APK aquí.</p>
                <a className="channel-app-download" href="/descargas/uebtv.apk" download>Descargar APK <span aria-hidden="true">↗</span></a>
                <small>Para Android y Android TV.</small>
            </div>
        </section>
    );
}

/** Miniatura de una emisión; sin imagen, una genérica con el logo de la UEB. */
export function GuideThumb({ image, live = false }) {
    return (
        <span className={`guide-thumb ${image ? "has-image" : ""}`} style={image ? { backgroundImage: `url(${JSON.stringify(image)})` } : undefined} aria-hidden="true">
            {!image && <img src="/images/brand/ueb-wordmark.png" alt="" />}
            {live && <i className="guide-thumb__live">EN VIVO</i>}
        </span>
    );
}

/** Identificador anónimo y estable de este navegador (no guarda cuentas ni IP). */
export function browserViewerKey() {
    const storageKey = "uebtv-viewer-id";
    let viewer;
    try { viewer = localStorage.getItem(storageKey); } catch {}
    if (!/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i.test(viewer || "")) {
        const bytes = new Uint8Array(16);
        if (globalThis.crypto?.getRandomValues) globalThis.crypto.getRandomValues(bytes);
        else for (let i = 0; i < bytes.length; i++) bytes[i] = Math.floor(Math.random() * 256);
        bytes[6] = (bytes[6] & 0x0f) | 0x40;
        bytes[8] = (bytes[8] & 0x3f) | 0x80;
        const hex = Array.from(bytes, (byte) => byte.toString(16).padStart(2, "0")).join("");
        viewer = `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`;
        try { localStorage.setItem(storageKey, viewer); } catch {}
    }
    return viewer;
}

/**
 * Avisa al servidor cada 20 s que este navegador está viendo el directo y
 * devuelve cuántas personas hay conectadas (null mientras no haya respuesta).
 * Se detiene cuando `active` es falso o la pestaña se cierra, y reenvía de
 * inmediato al volver a una pestaña que estaba oculta.
 */
export function useViewerPresence(url, active, csrf) {
    const [count, setCount] = useState(null);
    useEffect(() => {
        if (!active || !url) { setCount(null); return undefined; }
        const viewer = browserViewerKey();
        let stopped = false, timer, controller;
        async function heartbeat() {
            controller?.abort();
            controller = new AbortController();
            try {
                const response = await fetch(url, {
                    method: "POST",
                    signal: controller.signal,
                    credentials: "same-origin",
                    headers: { Accept: "application/json", "Content-Type": "application/json", ...(csrf ? { "X-CSRF-TOKEN": csrf } : {}) },
                    body: JSON.stringify({ viewer }),
                });
                const data = response.ok ? await response.json() : null;
                if (!stopped && Number.isInteger(data?.viewers)) setCount(data.viewers);
            } catch {}
            if (!stopped) timer = setTimeout(heartbeat, 20000);
        }
        heartbeat();
        const resume = () => { if (!document.hidden) { clearTimeout(timer); heartbeat(); } };
        document.addEventListener("visibilitychange", resume);
        return () => { stopped = true; clearTimeout(timer); controller?.abort(); document.removeEventListener("visibilitychange", resume); };
    }, [url, active, csrf]);
    return count;
}
