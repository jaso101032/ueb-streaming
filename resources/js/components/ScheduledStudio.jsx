import { durationLabel } from "../platform/Channel";
import { Action, useShell } from "../platform/shared";
import React, { useEffect, useRef, useState } from "react";
import { createPortal } from "react-dom";

function Icon({ name = "play", size = 22 }) {
    const paths = {
        play: <path d="m9 5 11 7-11 7Z" />,
        upload: (
            <>
                <path d="M12 16V3m-5 5 5-5 5 5M4 15v5a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-5" />
            </>
        ),
        clock: (
            <>
                <circle cx="12" cy="12" r="9" />
                <path d="M12 7v5l3 2" />
            </>
        ),
        plus: <path d="M12 5v14m-7-7h14" />,
        chart: <><path d="M4 19V5m0 14h16" /><path d="m7 15 3-4 3 2 5-6" /></>,
        arrow: <path d="M5 12h14m-5-5 5 5-5 5" />,
        check: <path d="m5 12 4 4L19 6" />,
        pause: <path d="M9 5v14M15 5v14" />,
        trash: <><path d="M4 7h16M10 11v6m4-6v6" /><path d="M6 7l1 12a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2l1-12M9 7V4h6v3" /></>,
        retry: <><path d="M20 11a8 8 0 1 0-2.3 5.7" /><path d="M20 5v6h-6" /></>,
    };
    return (
        <svg
            aria-hidden="true"
            width={size}
            height={size}
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth="1.6"
            strokeLinecap="round"
            strokeLinejoin="round"
        >
            {paths[name]}
        </svg>
    );
}
function Section({ number, title, subtitle, children }) {
    return (
        <section className="schedule-section">
            <div className="schedule-section-title">
                <span>{number}</span>
                <div>
                    <h2>{title}</h2>
                    <p>{subtitle}</p>
                </div>
            </div>
            {children}
        </section>
    );
}
function Field({ id, label, optional, children }) {
    return (
        <div className="uebtv-form-field">
            <label htmlFor={id}>
                {label}
                {optional ? <small>Opcional</small> : <span>*</span>}
            </label>
            {children}
        </div>
    );
}
const stateLabels = {
    upcoming: "Próximamente",
    playing: "En emisión",
    ended: "Disponible",
    pending: "En cola",
    processing: "Optimizando",
    ready: "Lista para emitir",
    failed: "Error de conversión",
    cancelled: "Cancelado",
    paused: "Pausado por directo",
};

function useVideoUpload() {
    const [upload, setUpload] = useState({ phase: "idle", percent: 0, error: "" });
    useEffect(() => {
        const reset = () => setUpload({ phase: "idle", percent: 0, error: "" });
        window.addEventListener("pageshow", reset);
        return () => window.removeEventListener("pageshow", reset);
    }, []);
    const submit = (event) => {
        event.preventDefault();
        if (upload.phase === "uploading" || upload.phase === "verifying") return;
        const form = event.currentTarget;
        const request = new XMLHttpRequest();
        request.open("POST", form.action);
        request.setRequestHeader("Accept", "application/json");
        request.setRequestHeader("X-Requested-With", "XMLHttpRequest");
        setUpload({ phase: "uploading", percent: 0, error: "" });
        request.upload.onprogress = (progress) => {
            if (progress.lengthComputable) {
                setUpload({ phase: "uploading", percent: Math.min(99, Math.round(progress.loaded / progress.total * 100)),
                    loaded: progress.loaded, total: progress.total, error: "" });
            }
        };
        request.upload.onload = () => setUpload((current) => ({ ...current, phase: "verifying", percent: 100, loaded: current.total || current.loaded }));
        request.onload = () => {
            let data = {};
            try { data = JSON.parse(request.responseText); } catch (_) { /* A proxy can return an HTML error page. */ }
            if (request.status === 201 && data.redirect) {
                window.location.assign(data.redirect);
                return;
            }
            const firstError = data.errors && Object.values(data.errors).flat()[0];
            const message = firstError || data.message || (request.status === 413
                ? "El archivo supera el límite del servidor."
                : "No se pudo subir el video. Revisa tu conexión e inténtalo de nuevo.");
            setUpload({ phase: "error", percent: 0, error: message });
        };
        request.onerror = () => setUpload({ phase: "error", percent: 0, error: "Se perdió la conexión durante la subida. Inténtalo de nuevo." });
        request.send(new FormData(form));
    };
    return { upload, submit, reset: () => setUpload({ phase: "idle", percent: 0, error: "" }) };
}

function UploadProgress({ upload }) {
    if (upload.phase === "idle") return null;
    if (upload.phase === "error") return <p className="schedule-upload-error" role="alert">{upload.error}</p>;
    return <div className="schedule-upload-progress" role="status" aria-live="polite">
        <span>{upload.phase === "verifying" ? "Subida completa. Verificando el archivo…" : `Subiendo video: ${upload.percent}%`}</span>
        <progress max="100" value={upload.percent} aria-label="Progreso de subida del video" />
        <small>{upload.total ? `${(upload.loaded / 1048576).toFixed(1)} de ${(upload.total / 1048576).toFixed(1)} MB enviados. ` : ""}Después de subirlo, la conversión tendrá su propio avance en esta página.</small>
    </div>;
}

function ConversionProgress({ status, percent }) {
    if (status !== "pending" && status !== "processing") return null;
    return <div className="schedule-conversion-progress" role="status">
        <span>{status === "pending" ? "En cola para convertir" : `Convirtiendo video: ${percent ?? 0}%`}</span>
        {status === "processing" && <progress max="100" value={percent ?? 0} aria-label="Progreso de conversión del video" />}
    </div>;
}

const human = (bytes) => bytes >= 1024 ** 3 ? `${(bytes / 1024 ** 3).toLocaleString("es-EC", { maximumFractionDigits: 1 })} GB` : `${Math.round(bytes / 1048576).toLocaleString("es-EC")} MB`;
/** Por qué no se puede subir un archivo de este tamaño (o "" si cabe). */
const sizeError = (file, limits) => !file || file.size <= limits.maxBytes ? ""
    : limits.reason === "space"
        ? `No hay espacio suficiente en el servidor para este video (${human(file.size)}). Ahora caben hasta ${human(limits.maxBytes)}.`
        : `El video (${human(file.size)}) supera el límite configurado en el servidor (${human(limits.maxBytes)}).`;
/** Aviso visible cuando ya no queda espacio para subir videos. */
function SpaceNotice({ limits }) {
    if (!limits.noSpace) return null;
    return <p className="sp-space-alert" role="alert"><strong>El servidor no tiene espacio libre para nuevos videos.</strong> Borra lo que ya no hace falta para poder subir. <FreeSpaceLink limits={limits} /></p>;
}
/** Acceso directo a "Liberar espacio" (Biblioteca de medios). */
function FreeSpaceLink({ limits }) {
    return limits.freeUrl ? <a className="sp-free-link" href={limits.freeUrl}>Liberar espacio →</a> : null;
}
const isSpaceError = (message) => /^No hay espacio suficiente/.test(message || "");

function InstitutionalAds({ action, csrf, ads, old, uploadLimits }) {
    const [title, setTitle] = useState(old.ad_title || "");
    const [fileError, setFileError] = useState("");
    const { upload, submit, reset } = useVideoUpload();
    const submitting = upload.phase === "uploading" || upload.phase === "verifying";
    const choose = (event) => {
        const file = event.target.files[0];
        const error = file && (!/\.(mp4|m4v|mov|mkv|webm|avi|wmv|mpeg|mpg)$/i.test(file.name)
            ? "Selecciona MP4, MOV, MKV, WebM, AVI, WMV o MPEG."
            : sizeError(file, uploadLimits));
        event.target.setCustomValidity(error || "");
        setFileError(error || "");
        reset();
    };
    return <section id="schedule-ads" className="ad-studio" aria-labelledby="ad-studio-title">
        <div className="ad-studio-heading">
            <div><p className="studio-eyebrow">CONTINUIDAD DE LA SEÑAL</p><h2 id="ad-studio-title">Publicidad institucional</h2>
                <p>Estos videos cubren los huecos de la señal continua. Un directo o un programa siempre tiene prioridad. La audiencia verá una «P» y no podrá adelantarlos.</p></div>
            <span className="schedule-pill">{ads.filter((ad) => ad.active && ad.status === "ready").length} anuncios activos</span>
        </div>
        <div className="ad-studio-grid">
            <form className="schedule-form ad-studio-form" method="POST" action={action} encType="multipart/form-data" onSubmit={submit} aria-busy={submitting}>
                <input type="hidden" name="_token" value={csrf} />
                <Section number="P" title="Añadir anuncio oficial" subtitle="Solo utiliza piezas institucionales autorizadas por la UEB.">
                    <Field id="ad-title" label="Nombre del anuncio">
                        <input id="ad-title" name="ad_title" maxLength={255} required value={title} onChange={(e) => setTitle(e.target.value)} placeholder="Ej. Campaña institucional UEB" />
                    </Field>
                    <Field id="ad-video" label="Archivo de video">
                        <input id="ad-video" name="ad_video" type="file" accept=".mp4,.m4v,.mov,.mkv,.webm,.avi,.wmv,.mpeg,.mpg" required onChange={choose} aria-describedby="ad-file-help ad-file-error" />
                    </Field>
                    <p id="ad-file-help" className="schedule-help">{uploadLimits.freeBytes != null ? `Espacio disponible: ${human(uploadLimits.maxBytes)}. ` : ""}El archivo se optimiza antes de emitirse y se repetirá con los otros anuncios durante los huecos.</p>
                    {fileError && <p id="ad-file-error" className="schedule-file-error" role="alert">{fileError}{isSpaceError(fileError) && <> <FreeSpaceLink limits={uploadLimits} /></>}</p>}
                </Section>
                <div className="schedule-submit"><SpaceNotice limits={uploadLimits} /><UploadProgress upload={upload} /><button type="submit" className="studio-button" disabled={submitting || uploadLimits.noSpace}>{submitting ? "Subiendo…" : "Subir publicidad"}<Icon name="upload" size={17} /></button></div>
            </form>
            <div className="studio-panel ad-studio-list">
                <div className="studio-panel-heading"><div><p className="studio-eyebrow">BIBLIOTECA INSTITUCIONAL</p><h3>Anuncios disponibles</h3></div><strong>{ads.length}</strong></div>
                {ads.length ? ads.map((ad) => <article className="ad-studio-item" key={ad.id}>
                    <div className="ad-studio-item__mark" aria-hidden="true">P</div>
                    <div className="ad-studio-item__body"><h4>{ad.title}</h4><p>{stateLabels[ad.status] || ad.status}{ad.status === "ready" ? ` · ${durationLabel(ad.duration)}` : ""}{ad.status === "ready" && !ad.active ? " · Fuera del aire" : ""}</p><ConversionProgress status={ad.status} percent={ad.progress} />{ad.error && <small role="status">{ad.error}</small>}</div>
                    <div className="ad-studio-item__actions">
                        {ad.status === "failed" && <Action url={ad.retryUrl} className="studio-button"><Icon name="retry" size={15} />Reintentar</Action>}
                        <Action url={ad.toggleUrl} className="studio-button" confirm={ad.active ? "¿Quitar este anuncio de la señal continua?" : undefined}><Icon name={ad.active ? "pause" : "play"} size={15} />{ad.active ? "Desactivar" : "Activar"}</Action>
                        {ad.status !== "processing" && <Action url={ad.deleteUrl} method="DELETE" className="studio-button studio-delete" confirm={`¿Eliminar «${ad.title}» definitivamente? Se borrarán el video y sus subtítulos; esta acción no se puede deshacer.`}><Icon name="trash" size={15} />Eliminar</Action>}
                    </div>
                </article>) : <div className="schedule-empty"><h4>Aún no hay anuncios</h4><p>Mientras no haya programación ni directo, la señal mostrará la pantalla de espera.</p></div>}
            </div>
        </div>
    </section>;
}

/* ───────────── Utilidades de fecha (zona horaria del canal) ───────────── */
const fmtDur = (seconds) => {
    const m = Math.max(1, Math.round((Number(seconds) || 0) / 60));
    return m < 60 ? `${m} min` : `${Math.floor(m / 60)} h${m % 60 ? ` ${String(m % 60).padStart(2, "0")} min` : ""}`;
};
const partsIn = (date, timeZone) => Object.fromEntries(new Intl.DateTimeFormat("en-CA", { timeZone, year: "numeric", month: "2-digit", day: "2-digit", hour: "2-digit", minute: "2-digit", hourCycle: "h23" })
    .formatToParts(date).filter((p) => p.type !== "literal").map((p) => [p.type, p.value]));
/** Valor para <input type="datetime-local"> en la zona del canal. */
const toInput = (date, timeZone) => { const p = partsIn(date, timeZone); return `${p.year}-${p.month}-${p.day}T${p.hour}:${p.minute}`; };
const dayKey = (date, timeZone) => { const p = partsIn(date, timeZone); return `${p.year}-${p.month}-${p.day}`; };
const clock = (date, timeZone) => new Date(date).toLocaleTimeString("es-EC", { timeZone, hour: "2-digit", minute: "2-digit", hourCycle: "h23" });
function dayLabel(key, timeZone) {
    const today = dayKey(new Date(), timeZone), tomorrow = dayKey(new Date(Date.now() + 864e5), timeZone);
    if (key === today) return "Hoy";
    if (key === tomorrow) return "Mañana";
    const [y, m, d] = key.split("-").map(Number);
    return new Date(Date.UTC(y, m - 1, d, 12)).toLocaleDateString("es-EC", { timeZone: "UTC", weekday: "long", day: "numeric", month: "long" });
}

function SIcon({ name }) {
    const paths = {
        plus: <path d="M12 5v14m-7-7h14" />,
        calendar: <><rect x="3.5" y="5" width="17" height="15" rx="2.5" /><path d="M8 3v4m8-4v4M3.5 10h17" /></>,
        list: <path d="M8 6h12M8 12h12M8 18h12M4 6h.01M4 12h.01M4 18h.01" />,
        megaphone: <path d="M4 10v4h3l7 4V6l-7 4H4Zm13-1.5a4 4 0 0 1 0 7" />,
        tv: <><rect x="3" y="6" width="18" height="12" rx="2" /><path d="M8 3l4 3 4-3" /></>,
        clock: <><circle cx="12" cy="12" r="8.5" /><path d="M12 7.5V12l3 2" /></>,
        spin: <path d="M20 11a8 8 0 1 0-2.3 5.7M20 5v6h-6" />,
        eye: <><path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12Z" /><circle cx="12" cy="12" r="3" /></>,
        gear: <><circle cx="12" cy="12" r="3" /><path d="M12 3v2m0 14v2M3 12h2m14 0h2M5.6 5.6l1.4 1.4m10 10 1.4 1.4m0-12.8-1.4 1.4m-10 10-1.4 1.4" /></>,
        trash: <><path d="M4 7h16M10 11v6m4-6v6" /><path d="M6 7l1 12a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2l1-12M9 7V4h6v3" /></>,
        upload: <path d="M12 16V4m-5 5 5-5 5 5M4 16v3a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-3" />,
        close: <path d="M6 6l12 12M18 6 6 18" />,
        out: <path d="M7 17 17 7M9 7h8v8" />,
        retry: <path d="M20 11a8 8 0 1 0-2.3 5.7M20 5v6h-6" />,
        image: <><rect x="3.5" y="4.5" width="17" height="15" rx="2.5" /><circle cx="9" cy="10" r="1.8" /><path d="m20.5 16-5-5L6 19.5" /></>,
        film: <><rect x="3.5" y="4" width="17" height="16" rx="2.5" /><path d="M7.5 4v16m9-16v16M3.5 9h4m9 0h4m-17 6h4m9 0h4" /></>,
        alert: <><path d="M12 3 2.5 20h19z" /><path d="M12 10v4m0 3v.1" /></>,
    };
    return <svg className="sp-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">{paths[name]}</svg>;
}
const Thumb = ({ image }) => <span className={`sp-thumb ${image ? "has-image" : ""}`} style={image ? { backgroundImage: `url(${JSON.stringify(image)})` } : undefined} aria-hidden="true">{!image && <img src="/images/brand/ueb-wordmark.png" alt="" />}</span>;

/* ───────────── Panel "Programar video" ───────────── */
const VISIBILITY = [
    ["publico", "Público", "Cualquier persona, sin iniciar sesión."],
    ["interno", "Comunidad UEB", "Solo personas con sesión iniciada."],
    ["restringido", "Solo el equipo", "Administradores y operadores."],
];

/**
 * Formulario de subida. mode="channel": video para la Parrilla 24/7 (elige
 * cuándo sale al aire). mode="catalog": video solo para el catálogo (elige
 * quién puede verlo y queda disponible cuando termina de prepararse).
 */
export function ProgramDrawer({ open, onClose, action, csrf, categories, faculties = [], careers = [], timezone, uploadLimits = {}, old = {}, start, setStart, endsAt, mode = "channel", editing = null }) {
    // editing: { values } para cambiar los datos de un video ya subido (el archivo no cambia).
    const catalog = mode === "catalog";
    const initial = editing?.values || old;
    const [visibility, setVisibility] = useState(initial.visibilidad || "publico");
    const [title, setTitle] = useState(initial.titulo || "");
    const [category, setCategory] = useState(initial.categoria_id ? String(initial.categoria_id) : "");
    const [faculty, setFaculty] = useState(initial.facultad_id ? String(initial.facultad_id) : "");
    const [career, setCareer] = useState(initial.carrera_id ? String(initial.carrera_id) : "");
    const facultyCareers = careers.filter((c) => String(c.facultad_id) === faculty);
    const [keptImage, setKeptImage] = useState(editing?.values?.image || "");
    const [file, setFile] = useState(null);
    const [fileError, setFileError] = useState("");
    const [duration, setDuration] = useState(0);
    const [thumb, setThumb] = useState(null);
    const [thumbError, setThumbError] = useState("");
    const [thumbUrl, setThumbUrl] = useState("");
    const thumbInput = useRef(null);
    useEffect(() => {
        if (!thumb) { setThumbUrl(""); return undefined; }
        const url = URL.createObjectURL(thumb);
        setThumbUrl(url);
        return () => URL.revokeObjectURL(url);
    }, [thumb]);
    const removeThumb = () => { if (thumbInput.current) thumbInput.current.value = ""; setThumb(null); setThumbError(""); setKeptImage(""); };
    const coverUrl = thumbUrl || keptImage;
    const [drag, setDrag] = useState(false);
    const videoInput = useRef(null), titleInput = useRef(null), panel = useRef(null);
    const { upload, submit, reset } = useVideoUpload();
    const submitting = upload.phase === "uploading" || upload.phase === "verifying";
    useEffect(() => {
        if (!open) return undefined;
        window.setTimeout(() => panel.current?.querySelector("button, input")?.focus({ preventScroll: true }), 60);
        const key = (event) => { if (event.key === "Escape" && !submitting) onClose(); };
        document.addEventListener("keydown", key);
        return () => document.removeEventListener("keydown", key);
    }, [open, submitting, onClose]);
    const pickVideo = (selected) => {
        setDuration(0);
        const error = selected && (!/\.(mp4|m4v|mov|mkv|webm|avi|wmv|mpeg|mpg)$/i.test(selected.name)
            ? "Selecciona MP4, MOV, MKV, WebM, AVI, WMV o MPEG."
            : sizeError(selected, uploadLimits));
        videoInput.current?.setCustomValidity(error || "");
        setFileError(error || "");
        setFile(error ? null : selected || null);
        if (selected && !error && !title.trim()) setTitle(selected.name.replace(/\.[^.]+$/, "").replace(/[_-]+/g, " ").replace(/\s+/g, " ").trim());
        if (selected && !error) {
            const url = URL.createObjectURL(selected);
            const probe = document.createElement("video");
            probe.preload = "metadata";
            probe.onloadedmetadata = () => { if (Number.isFinite(probe.duration)) setDuration(Math.ceil(probe.duration)); URL.revokeObjectURL(url); };
            probe.onerror = () => URL.revokeObjectURL(url);
            probe.src = url;
        }
        reset();
    };
    const pickThumb = (event) => {
        const selected = event.target.files[0];
        const error = selected && (!/\.(jpe?g|png|webp)$/i.test(selected.name) ? "Selecciona una imagen JPG, PNG o WebP."
            : selected.size > 2 * 1024 * 1024 ? "La imagen de portada no puede superar los 2 MB." : "");
        event.target.setCustomValidity(error || "");
        setThumbError(error || "");
        setThumb(error ? null : selected || null);
    };
    const soon = (minutes) => toInput(new Date(Date.now() + minutes * 60000), timezone);
    const afterLast = () => {
        const base = Math.max(Date.now() + 2 * 60000, endsAt ? new Date(endsAt).getTime() : 0);
        return toInput(new Date(Math.ceil(base / 60000) * 60000), timezone);
    };
    const tomorrow8 = () => `${dayKey(new Date(Date.now() + 864e5), timezone)}T08:00`;
    const quick = [["after", "Al terminar lo programado", afterLast], ["soon", "En 15 minutos", () => soon(15)], ["tomorrow", "Mañana 08:00", tomorrow8]];
    // Fuera de la página (su animación de entrada crea un contexto que
    // dejaría el panel debajo de la cabecera y más alto que la pantalla).
    return createPortal(
        <div className="sp-portal">
            <div className={`sp-scrim ${open ? "is-open" : ""}`} onClick={() => { if (!submitting) onClose(); }} aria-hidden="true" />
            <div className="sp-drawer-wrap">
            <section ref={panel} className={`sp-drawer ${open ? "is-open" : ""}`} role="dialog" aria-modal="true" aria-labelledby="sp-drawer-title" aria-hidden={!open} inert={!open}>
                <header className="sp-drawer__head">
                    <div><p className="sp-eyebrow">{catalog ? "Catálogo" : "Parrilla 24/7"}</p><h2 id="sp-drawer-title">{editing ? "Editar video" : catalog ? "Subir video al catálogo" : "Programar video"}</h2></div>
                    <button type="button" className="sp-icon-btn" onClick={onClose} disabled={submitting} aria-label="Cerrar"><SIcon name="close" /></button>
                </header>
                <form id="schedule-program-form" className="sp-form" method="POST" action={action} encType="multipart/form-data" onSubmit={submit} aria-busy={submitting}>
                    <input type="hidden" name="_token" value={csrf} />
                    {editing && <input type="hidden" name="_method" value="PUT" />}
                    {editing && !keptImage && !thumb && editing.values?.image && <input type="hidden" name="quitar_miniatura" value="1" />}
                    {!editing && <>
                    <div className="sp-step"><span>1</span><h3>Video</h3></div>
                    <label htmlFor="video" className={`sp-drop ${drag ? "is-drag" : ""} ${file ? "has-file" : ""}`}
                        onDragOver={(e) => { e.preventDefault(); setDrag(true); }} onDragLeave={() => setDrag(false)}
                        onDrop={(e) => { e.preventDefault(); setDrag(false); const f = e.dataTransfer.files?.[0]; if (f && videoInput.current) { const dt = new DataTransfer(); dt.items.add(f); videoInput.current.files = dt.files; pickVideo(f); } }}>
                        <span className="sp-drop__icon"><SIcon name={file ? "film" : "upload"} /></span>
                        {file ? <span className="sp-drop__text"><strong>{file.name}</strong><small>{(file.size / 1048576).toFixed(1)} MB{duration ? ` · ${fmtDur(duration)}` : ""} · Toca para cambiar</small></span>
                            : <span className="sp-drop__text"><strong>Arrastra el video aquí o haz clic para elegirlo</strong><small>MP4, MOV, MKV, WebM, AVI, WMV o MPEG · Sin límite de tamaño{uploadLimits.freeBytes != null ? ` · Espacio disponible: ${human(uploadLimits.maxBytes)}` : ""}</small></span>}
                        <input ref={videoInput} id="video" name="video" type="file" accept=".mp4,.m4v,.mov,.mkv,.webm,.avi,.wmv,.mpeg,.mpg" required className="ct-sr" onChange={(e) => pickVideo(e.target.files[0])} aria-describedby="sp-video-error" />
                    </label>
                    {fileError && <p id="sp-video-error" className="sp-error" role="alert">{fileError}{isSpaceError(fileError) && <> <FreeSpaceLink limits={uploadLimits} /></>}</p>}
                    <SpaceNotice limits={uploadLimits} />
                    {uploadLimits.serverLimited && uploadLimits.reason === "server" && <p className="sp-hint">La configuración de este servidor acepta hasta {human(uploadLimits.maxBytes)} por video.</p>}
                    </>}

                    <div className="sp-step"><span>{editing ? 1 : 2}</span><h3>Datos</h3></div>
                    <label className="sp-field"><span>{catalog ? "Título del video" : "Título de la emisión"}</span><input ref={titleInput} id="title" name="titulo" required maxLength={255} value={title} onChange={(e) => setTitle(e.target.value)} placeholder="Ej. Conferencia: Innovación en la UEB" /></label>
                    <label className="sp-field"><span>Categoría</span><select id="category" name="categoria_id" required value={category} onChange={(e) => setCategory(e.target.value)}><option value="">Selecciona una categoría</option>{categories.map((c) => <option key={c.id} value={c.id}>{c.nombre}</option>)}</select></label>
                    {faculties.length > 0 && <div className="sp-pair">
                        <label className="sp-field"><span>Facultad <small>(opcional)</small></span><select name="facultad_id" value={faculty} onChange={(e) => { setFaculty(e.target.value); setCareer(""); }}><option value="">Toda la institución</option>{faculties.map((f) => <option key={f.id} value={f.id}>{f.nombre}</option>)}</select></label>
                        <label className="sp-field"><span>Carrera <small>(opcional)</small></span><select name="carrera_id" value={career} onChange={(e) => setCareer(e.target.value)} disabled={!faculty}><option value="">{faculty ? "Toda la facultad" : "Elige primero la facultad"}</option>{facultyCareers.map((c) => <option key={c.id} value={c.id}>{c.nombre}</option>)}</select></label>
                    </div>}
                    {faculties.length > 0 && <p className="sp-hint sp-hint--tight">Con facultad y carrera, el video se agrupa con los demás de esa carrera en el catálogo y en «Más de…».</p>}
                    <div className="sp-field">
                        <span>Portada <small>(opcional)</small></span>
                        {/* Vista previa tal como se verá en el catálogo (16:9). */}
                        <div className={`sp-cover ${coverUrl ? "has-image" : ""}`}>
                            <span className="sp-cover__img" style={coverUrl ? { backgroundImage: `url(${JSON.stringify(coverUrl)})` } : undefined} aria-hidden="true">
                                {!coverUrl && <img src="/images/brand/ueb-wordmark.png" alt="" />}
                                <span className="sp-cover__tag">{coverUrl ? (thumb ? "Así se verá" : "Portada actual") : "Portada genérica"}</span>
                            </span>
                            <span className="sp-cover__side">
                                <strong>{thumb ? thumb.name : keptImage ? "Portada actual" : "Sin portada"}</strong>
                                <small>{thumb ? `${(thumb.size / 1024 / 1024).toFixed(2)} MB` : keptImage ? "Puedes cambiarla o quitarla." : "Se usará el logo de la UEB. JPG, PNG o WebP, hasta 2 MB."}</small>
                                <span className="sp-cover__actions">
                                    <label htmlFor="thumbnail" className="sp-btn"><SIcon name="image" />{thumb || keptImage ? "Cambiar" : "Elegir imagen"}</label>
                                    {(thumb || keptImage) && <button type="button" className="sp-btn is-danger" onClick={removeThumb}><SIcon name="trash" />Quitar</button>}
                                </span>
                            </span>
                            <input ref={thumbInput} id="thumbnail" name="miniatura" type="file" accept=".jpg,.jpeg,.png,.webp" className="ct-sr" onChange={pickThumb} aria-label="Imagen de portada" />
                        </div>
                        {thumbError && <p className="sp-error" role="alert">{thumbError}</p>}
                    </div>
                    <details className="sp-more" open={Boolean(initial.descripcion) || undefined}>
                        <summary>Descripción <small>(opcional)</small></summary>
                        <label className="sp-field"><span>Descripción</span><textarea id="description" name="descripcion" rows={3} maxLength={20000} defaultValue={initial.descripcion || ""} placeholder="Cuéntale a tu audiencia qué va a encontrar…" /></label>
                    </details>

                    {editing && !catalog ? null : catalog ? <>
                        <div className="sp-step"><span>{editing ? 2 : 3}</span><h3>¿Quién puede verlo?</h3></div>
                        <div className="sp-vis" role="radiogroup" aria-label="Quién puede ver el video">
                            {VISIBILITY.map(([value, label, help]) => <label key={value} className={`sp-vis__opt ${visibility === value ? "is-active" : ""}`}>
                                <input type="radio" name="visibilidad" value={value} checked={visibility === value} onChange={() => setVisibility(value)} />
                                <span><strong>{label}</strong><small>{help}</small></span>
                            </label>)}
                        </div>
                        {!editing && <p className="sp-hint">No se emite en el canal 24/7. Aparece en el catálogo en cuanto termina de prepararse; las calidades (1080p a 360p) y los subtítulos se agregan solos después.</p>}
                    </> : <>
                        <div className="sp-step"><span>3</span><h3>¿Cuándo sale al aire?</h3></div>
                        <div className="sp-quick" role="group" aria-label="Horarios rápidos">
                            {quick.map(([key, label, value]) => { const v = value(); return <button type="button" key={key} className={start === v ? "is-active" : ""} onClick={() => setStart(v)}>{label}</button>; })}
                        </div>
                        <label className="sp-field"><span>Fecha y hora de inicio</span><input id="start" type="datetime-local" name="fecha_inicio" required value={start} min={soon(1)} onChange={(e) => setStart(e.target.value)} /></label>
                        <p className="sp-hint">Zona horaria: {timezone}. Si a esa hora hay otro video, este espera su turno; un directo siempre tiene prioridad.</p>
                    </>}

                    <footer className="sp-drawer__foot">
                        <UploadProgress upload={upload} />
                        <div className="sp-drawer__buttons">
                            <button type="button" className="sp-btn" onClick={onClose} disabled={submitting}>Cancelar</button>
                            <button type="submit" className="sp-btn is-primary" disabled={submitting || (!editing && uploadLimits.noSpace)}>{submitting ? (editing ? "Guardando…" : "Subiendo…") : editing ? "Guardar cambios" : catalog ? "Subir video" : "Subir y programar emisión"}</button>
                        </div>
                    </footer>
                </form>
            </section>
            </div>
        </div>,
        document.body,
    );
}

export default function ScheduledStudio({
    action, adAction, ads = [], progressUrl, csrf, timezone, categories, events, pagination, old, errors, success,
    summary, channelUrl, uploadLimits, agenda = [], adsReady = 0, faculties = [], careers = [],
}) {
    const [eventRows, setEventRows] = useState(events);
    const [adRows, setAdRows] = useState(ads);
    const initialTab = typeof window !== "undefined" && window.location.hash === "#schedule-ads" ? "ads" : "agenda";
    const [tab, setTab] = useState(initialTab);
    const [drawer, setDrawer] = useState((errors.length > 0 && !!old.titulo) || (typeof window !== "undefined" && window.location.hash === "#programar"));
    const [start, setStart] = useState(old.fecha_inicio || "");
    const [, tick] = useState(0);
    useEffect(() => { const t = window.setInterval(() => tick((n) => n + 1), 30000); return () => window.clearInterval(t); }, []);
    const hasConversions = eventRows.some((event) => ["pending", "processing"].includes(event.state))
        || adRows.some((ad) => ["pending", "processing"].includes(ad.status));
    useEffect(() => {
        if (!hasConversions || !progressUrl) return;
        const controller = new AbortController();
        const refresh = async () => {
            if (document.visibilityState === "hidden") return;
            try {
                const response = await fetch(progressUrl, { signal: controller.signal, credentials: "same-origin", cache: "no-store" });
                if (!response.ok) return;
                const data = await response.json();
                setEventRows((rows) => rows.map((event) => {
                    const current = data.events?.[event.id];
                    if (!current) return event;
                    const state = current.status === "ready" ? (["pending", "processing", "failed"].includes(event.state) ? "ready" : event.state) : current.status;
                    return { ...event, state, progress: current.progress, error: current.error };
                }));
                setAdRows((rows) => rows.map((ad) => data.ads?.[ad.id] ? { ...ad, status: data.ads[ad.id].status, progress: data.ads[ad.id].progress, error: data.ads[ad.id].error } : ad));
            } catch (error) { if (error.name !== "AbortError") { /* Se reintenta en la siguiente consulta. */ } }
        };
        refresh();
        const timer = window.setInterval(refresh, 5000);
        return () => { controller.abort(); window.clearInterval(timer); };
    }, [hasConversions, progressUrl]);
    const openDrawer = (at) => { if (at) setStart(at); setDrawer(true); };

    // Agenda con huecos libres entre emisiones.
    const now = Date.now();
    const rows = [];
    let cursor = now;
    for (const item of agenda) {
        const s = new Date(item.startsAt).getTime(), e = new Date(item.endsAt).getTime();
        if (e <= now) continue;
        if (s - cursor > 60000) rows.push({ type: "gap", from: cursor, to: s });
        rows.push({ type: "item", item, from: s });
        cursor = Math.max(cursor, e);
    }
    rows.push({ type: "gap", from: cursor, to: null });
    const days = [];
    for (const row of rows) {
        const key = dayKey(new Date(Math.max(row.from, now)), timezone);
        let day = days.find((d) => d.key === key);
        if (!day) days.push(day = { key, rows: [] });
        day.rows.push(row);
    }
    const onAir = agenda.find((i) => i.state === "playing" || i.state === "paused");
    const preparing = eventRows.filter((e) => ["pending", "processing", "failed"].includes(e.state));
    const endsAtText = summary.endsAt && new Date(summary.endsAt).getTime() > now
        ? `${dayLabel(dayKey(new Date(summary.endsAt), timezone), timezone)} · ${clock(summary.endsAt, timezone)}` : "Nada programado";
    const gapStartValue = (from) => toInput(new Date(Math.ceil(Math.max(from, now + 2 * 60000) / 60000) * 60000), timezone);

    return (
        <div className="studio-page schedule-page sp">
            <header className="sp-head">
                <div>
                    <p className="sp-eyebrow">Programación del canal</p>
                    <h1>Parrilla 24/7</h1>
                    <p className="sp-lead">Sube videos y elige cuándo salen al aire. Los huecos se cubren con publicidad institucional.</p>
                </div>
                <div className="sp-head__actions">
                    <a className="sp-btn" href={channelUrl} target="_blank" rel="noopener"><SIcon name="out" />Ver la señal</a>
                    <button type="button" className="sp-btn is-primary" onClick={() => openDrawer()}><SIcon name="plus" />Programar video</button>
                </div>
            </header>
            {success && <p className="studio-alert" role="status">{success}</p>}
            {errors.length > 0 && <div className="uebtv-form-errors" role="alert">{errors.map((error, i) => <p key={i}>{error}</p>)}</div>}

            <section className="sp-status" aria-label="Estado de la señal">
                <div className={`sp-stat ${onAir ? "is-live" : ""}`}>
                    <span className="sp-stat__icon"><SIcon name="tv" /></span>
                    <span><small>Al aire ahora</small><strong>{onAir ? onAir.title : adsReady ? "Publicidad institucional" : "Sin emisión"}</strong>{onAir && <em>{onAir.state === "paused" ? "En pausa por un directo" : `Termina a las ${clock(onAir.endsAt, timezone)}`}</em>}</span>
                </div>
                <div className="sp-stat"><span className="sp-stat__icon"><SIcon name="calendar" /></span><span><small>Programado hasta</small><strong>{endsAtText}</strong></span></div>
                <div className={`sp-stat ${preparing.some((e) => e.state === "failed") ? "is-bad" : ""}`}><span className="sp-stat__icon"><SIcon name="spin" /></span><span><small>En preparación</small><strong>{preparing.length ? `${preparing.length} ${preparing.length === 1 ? "video" : "videos"}` : "Ninguno"}</strong></span></div>
                <div className="sp-stat"><span className="sp-stat__icon"><SIcon name="megaphone" /></span><span><small>Publicidad activa</small><strong>{adsReady ? `${adsReady} ${adsReady === 1 ? "anuncio" : "anuncios"}` : "Ninguna"}</strong></span></div>
            </section>

            <nav className="sp-tabs" aria-label="Navegación rápida de la parrilla">
                <button type="button" aria-pressed={tab === "agenda"} onClick={() => setTab("agenda")}><SIcon name="calendar" />Agenda</button>
                <button type="button" aria-pressed={tab === "events"} onClick={() => setTab("events")}><SIcon name="list" />Todos los videos <b>{pagination.total}</b></button>
                <button type="button" aria-pressed={tab === "ads"} onClick={() => setTab("ads")}><SIcon name="megaphone" />Publicidad <b>{adRows.length}</b></button>
            </nav>

            {tab === "agenda" && <div className="sp-agenda">
                {preparing.length > 0 && <section className="sp-day" aria-labelledby="sp-prep-title">
                    <h2 id="sp-prep-title" className="sp-day__title">En preparación <small>aparecerán en la agenda cuando estén listos</small></h2>
                    <ul className="sp-list">
                        {preparing.map((e) => <li key={e.id} className={`sp-row is-prep ${e.state === "failed" ? "is-bad" : ""}`}>
                            <span className="sp-row__time"><SIcon name={e.state === "failed" ? "alert" : "spin"} /></span>
                            <Thumb image={e.image} />
                            <span className="sp-row__text"><strong>{e.title}</strong>
                                {e.state === "failed" ? <small className="sp-bad">{e.error || "No se pudo convertir el video."}</small>
                                    : <span className="sp-bar"><span style={{ width: `${e.state === "processing" ? e.progress || 0 : 0}%` }} /><small>{e.state === "processing" ? `Convirtiendo · ${e.progress || 0} %` : "En cola para convertir"}</small></span>}
                            </span>
                            <span className="sp-row__state">{e.state === "failed" ? <span className="sp-badge is-failed">Error</span> : <span className={`sp-badge is-${e.state}`}>{e.state === "processing" ? "Convirtiendo" : "En cola"}</span>}</span>
                            <span className="sp-row__actions">{e.state === "failed" && <Action url={e.retryUrl} className="studio-icon-action" ariaLabel={`Reintentar ${e.title}`} tooltip="Reintentar"><SIcon name="retry" /></Action>}</span>
                        </li>)}
                    </ul>
                </section>}
                {days.map((day) => <section className="sp-day" key={day.key} aria-label={dayLabel(day.key, timezone)}>
                    <h2 className="sp-day__title">{dayLabel(day.key, timezone)}</h2>
                    <ul className="sp-list">
                        {day.rows.map((row, i) => row.type === "item" ? (
                            <li key={`i-${row.item.id}`} className={`sp-row ${row.item.state !== "upcoming" ? "is-live" : ""}`}>
                                <span className="sp-row__time"><strong>{clock(row.item.startsAt, timezone)}</strong><small>{clock(row.item.endsAt, timezone)}</small></span>
                                <Thumb image={row.item.image} />
                                <span className="sp-row__text">
                                    <strong>{row.item.title}</strong>
                                    <small>{[row.item.category, fmtDur(row.item.duration)].filter(Boolean).join(" · ")}</small>
                                </span>
                                <span className="sp-row__state">{row.item.state === "playing" ? <span className="sp-badge is-live"><i />AL AIRE</span> : row.item.state === "paused" ? <span className="sp-badge is-paused">EN PAUSA</span> : <span className="sp-badge">Programado</span>}</span>
                                <span className="sp-row__actions">
                                    <a className="studio-icon-action" href={row.item.url} target="_blank" rel="noopener" aria-label={`Ver la sala de ${row.item.title}`}><SIcon name="eye" /><span className="studio-action-tooltip" role="tooltip">Ver sala</span></a>
                                    <a className="studio-icon-action" href={row.item.adminUrl} aria-label={`Detalles de ${row.item.title}`}><SIcon name="gear" /><span className="studio-action-tooltip" role="tooltip">Detalles</span></a>
                                    <Action url={row.item.deleteUrl} method="DELETE" className="studio-icon-action is-danger" ariaLabel={`Eliminar ${row.item.title}`} tooltip="Eliminar" confirm={`¿Eliminar «${row.item.title}» de la parrilla? Se quitará también del catálogo.`}><SIcon name="trash" /></Action>
                                </span>
                            </li>
                        ) : (
                            <li key={`g-${i}`} className="sp-gap">
                                <span className="sp-row__time"><strong>{clock(new Date(Math.max(row.from, now)), timezone)}</strong>{row.to && <small>{clock(row.to, timezone)}</small>}</span>
                                <span className="sp-gap__text">
                                    <strong>{row.to ? `Libre · ${fmtDur((row.to - Math.max(row.from, now)) / 1000)}` : "Libre desde aquí"}</strong>
                                    <small>{adsReady ? "Se cubre con publicidad institucional" : "La señal mostrará la pantalla de espera"}</small>
                                </span>
                                <button type="button" className="sp-btn is-ghost" onClick={() => openDrawer(gapStartValue(row.from))}><SIcon name="plus" />Programar aquí</button>
                            </li>
                        ))}
                    </ul>
                </section>)}
            </div>}

            {tab === "events" && <section id="schedule-events" className="sp-all" aria-labelledby="schedule-events-title">
                <h2 id="schedule-events-title" className="ct-sr">Todos los videos programados</h2>
                {eventRows.length ? <ul className="sp-list">
                    {eventRows.map((event) => <li className={`sp-row schedule-event ${event.state === "failed" ? "is-bad" : ""}`} key={event.id}>
                        <span className="sp-row__time"><strong>{event.day}/{event.month.slice(0, 2)}</strong><small>{event.time}</small></span>
                        <Thumb image={event.image} />
                        <span className="sp-row__text">
                            <strong>{event.title}</strong>
                            <small>{[event.category, `${event.minutes} min`, event.estimatedStart ? `sale ${new Date(event.estimatedStart).toLocaleString("es-EC", { timeZone: timezone, dateStyle: "short", timeStyle: "short" })}` : null].filter(Boolean).join(" · ")}</small>
                            <ConversionProgress status={event.state} percent={event.progress} />
                            {event.error && <small className="sp-bad">{event.error}</small>}
                        </span>
                        <span className="sp-row__state"><span className={`sp-badge is-${event.state}`}>{stateLabels[event.state]}</span></span>
                        <span className="sp-row__actions">
                            {event.state === "failed" && <Action url={event.retryUrl} className="studio-icon-action" ariaLabel={`Reintentar ${event.title}`} tooltip="Reintentar"><SIcon name="retry" /></Action>}
                            <a className="studio-icon-action" href={event.url} target="_blank" rel="noopener" aria-label={`Ver la sala de ${event.title}`}><SIcon name="eye" /><span className="studio-action-tooltip" role="tooltip">Ver sala</span></a>
                            {event.adminUrl && <a className="studio-icon-action" href={event.adminUrl} aria-label={`Detalles de ${event.title}`}><SIcon name="gear" /><span className="studio-action-tooltip" role="tooltip">Detalles</span></a>}
                            {event.deleteUrl && <Action url={event.deleteUrl} method="DELETE" className="studio-icon-action is-danger" ariaLabel={`Eliminar ${event.title}`} tooltip="Eliminar" confirm={`¿Eliminar «${event.title}»? Se quitará de la parrilla y del catálogo.`}><SIcon name="trash" /></Action>}
                        </span>
                    </li>)}
                </ul> : <div className="sp-empty"><SIcon name="calendar" /><h3>Aún no hay videos programados</h3><p>Sube el primero y elige cuándo sale al aire.</p><button type="button" className="sp-btn is-primary" onClick={() => openDrawer()}><SIcon name="plus" />Programar video</button></div>}
                {pagination.last > 1 && <nav className="schedule-pagination" aria-label="Páginas de emisiones">
                    {pagination.previous && <a href={pagination.previous}>← Anterior</a>}<span>Página {pagination.current} de {pagination.last}</span>{pagination.next && <a href={pagination.next}>Siguiente →</a>}
                </nav>}
            </section>}

            {tab === "ads" && <InstitutionalAds action={adAction} csrf={csrf} ads={adRows} old={old} uploadLimits={uploadLimits} />}

            <ProgramDrawer open={drawer} onClose={() => setDrawer(false)} action={action} csrf={csrf} categories={categories} faculties={faculties} careers={careers} timezone={timezone}
                uploadLimits={uploadLimits} old={old} start={start} setStart={setStart} endsAt={summary.endsAt} />
        </div>
    );
}
