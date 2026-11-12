import React, { createContext, useContext, useState, useEffect } from "react";
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
    skipped: "Este video no tiene subtítulos. Puedes generarlos automáticamente o subir un archivo.",
};

/** Subtítulos de un video almacenado: automáticos (Whisper) o subidos (.vtt/.srt). */
export function SubtitleForm({ subtitles, idPrefix = "subtitles" }) {
    if (!subtitles) return null;
    const inputId = `${idPrefix}-file`;
    const { present, source, status, progress, error } = subtitles;
    const working = !present && (status === "pending" || status === "processing");
    let message = null;
    if (present) {
        message = source === "auto"
            ? "Subtítulos generados automáticamente. Revísalos: si encuentras errores, descarga el archivo, corrígelo y súbelo con “Reemplazar”."
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
                        {status === "failed" ? "Reintentar subtítulos automáticos" : "Generar subtítulos automáticos"}
                    </Action>
                )}
                {present && subtitles.previewUrl && (
                    <a className="studio-button studio-button-soft" href={subtitles.previewUrl} download="subtitulos.vtt">Descargar .vtt</a>
                )}
                {present && subtitles.removeUrl && (
                    <Action url={subtitles.removeUrl} method="DELETE" className="studio-button studio-button-soft" confirm="¿Quitar los subtítulos de este video? No se volverán a generar solos.">
                        Quitar subtítulos
                    </Action>
                )}
            </div>
            <form method="POST" action={subtitles.uploadUrl} encType="multipart/form-data">
                <Token />
                <label htmlFor={inputId}>
                    {present ? "Reemplazar subtítulos" : "Subir un archivo propio"}
                    <small> · archivo .vtt o .srt, máximo 1 MB</small>
                </label>
                <input id={inputId} name="subtitles" type="file" accept=".vtt,.srt" required />
                <button className="studio-button studio-button-soft">
                    {present ? "Reemplazar" : "Subir subtítulos"}
                </button>
            </form>
        </div>
    );
}
