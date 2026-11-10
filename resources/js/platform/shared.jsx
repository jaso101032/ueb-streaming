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

/** Subtítulos (.vtt o .srt) de un video almacenado: subir, reemplazar o quitar. */
export function SubtitleForm({ subtitles, idPrefix = "subtitles" }) {
    if (!subtitles) return null;
    const inputId = `${idPrefix}-file`;
    return (
        <div className="subtitle-form">
            <form method="POST" action={subtitles.uploadUrl} encType="multipart/form-data">
                <Token />
                <label htmlFor={inputId}>
                    {subtitles.present ? "Reemplazar subtítulos" : "Agregar subtítulos"}
                    <small> · archivo .vtt o .srt, máximo 1 MB</small>
                </label>
                <input id={inputId} name="subtitles" type="file" accept=".vtt,.srt" required />
                <button className="studio-button studio-button-soft">
                    {subtitles.present ? "Reemplazar" : "Subir subtítulos"}
                </button>
            </form>
            {subtitles.present && subtitles.removeUrl && (
                <Action url={subtitles.removeUrl} method="DELETE" className="studio-button studio-button-soft" confirm="¿Quitar los subtítulos de este video?">
                    Quitar subtítulos
                </Action>
            )}
        </div>
    );
}
