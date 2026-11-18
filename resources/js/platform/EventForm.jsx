import React, { useEffect, useRef, useState } from "react";
import { Token, Feedback, Field } from "./shared";
export default function EventForm({
    editing,
    action,
    values,
    types,
    categories,
    faculties,
    careers,
    image,
    back,
    recordingSettingsLocked = false,
    visibilities = [],
    licenses = [],
    canToggleChat = false,
}) {
    const [v, setV] = useState(values),
        [file, setFile] = useState(null),
        [preview, setPreview] = useState(image),
        [busy, setBusy] = useState(false);
    const end = useRef();
    useEffect(() => {
        if (!file) {
            setPreview(image);
            return;
        }
        const url = URL.createObjectURL(file);
        setPreview(url);
        return () => URL.revokeObjectURL(url);
    }, [file, image]);
    useEffect(() => {
        end.current?.setCustomValidity(
            v.fecha_fin && v.fecha_inicio && v.fecha_fin <= v.fecha_inicio
                ? "El cierre debe ser posterior al inicio."
                : "",
        );
    }, [v.fecha_inicio, v.fecha_fin]);
    useEffect(() => {
        const reset = () => setBusy(false);
        window.addEventListener("pageshow", reset);
        return () => window.removeEventListener("pageshow", reset);
    }, []);
    const bind = (n) => ({
        id: n,
        name: n,
        value: v[n] ?? "",
        onChange: (e) =>
            setV((x) => ({
                ...x,
                [n]: e.target.value,
                ...(n === "facultad_id" ? { carrera_id: "" } : {}),
            })),
    });
    const select = (n, label, options, required = false) => (
        <Field name={n} label={label}>
            <select {...bind(n)} required={required}>
                <option value="">
                    {required
                        ? "Selecciona una opción"
                        : "Toda la institución / Sin especificar"}
                </option>
                {options.map((o) => (
                    <option key={o.id} value={o.id}>
                        {o.nombre}
                    </option>
                ))}
            </select>
        </Field>
    );
    return (
        <div className="uebtv-form-page uebtv-event-editor">
            <div className="uebtv-form-container">
                <a href={back} className="uebtv-form-back">
                    ← Volver a eventos
                </a>
                <section className="uebtv-form-heading">
                    <div>
                        <p className="studio-eyebrow">
                            PROGRAMACIÓN INSTITUCIONAL
                        </p>
                        <h1>{editing ? "Editar evento" : "Crear evento"}</h1>
                        <p>Dale un espacio a tu próxima gran idea.</p>
                    </div>
                </section>
                <Feedback />
                {!categories.length && (
                    <p role="alert" className="uebtv-form-errors">
                        Todavía no hay categorías disponibles. Configura el
                        catálogo antes de crear un evento.
                    </p>
                )}
                <form
                    action={action}
                    method="POST"
                    encType="multipart/form-data"
                    className="uebtv-event-form"
                    onSubmit={() => setBusy(true)}
                >
                    <Token method={editing ? "PUT" : undefined} />
                    <div className="uebtv-form-main">
                        <section className="uebtv-form-section">
                            <div className="uebtv-form-section__title">
                                <span>01</span>
                                <div>
                                    <h2>Información principal</h2>
                                    <p>Los campos con * son obligatorios.</p>
                                </div>
                            </div>
                            <div className="uebtv-form-fields">
                                <Field wide name="titulo" label="Título *">
                                    <input
                                        {...bind("titulo")}
                                        maxLength="255"
                                        required
                                        placeholder="El próximo gran encuentro"
                                    />
                                </Field>
                                {select(
                                    "tipo_evento",
                                    "Tipo de evento *",
                                    types.map((t) => ({ id: t, nombre: t })),
                                    true,
                                )}
                                {select(
                                    "categoria_id",
                                    "Categoría *",
                                    categories,
                                    true,
                                )}
                                {select("facultad_id", "Facultad", faculties)}
                                {select(
                                    "carrera_id",
                                    "Carrera",
                                    careers.filter(
                                        (c) =>
                                            String(c.facultad_id) ===
                                            String(v.facultad_id),
                                    ),
                                )}
                                <Field
                                    name="fecha_inicio"
                                    label="Fecha y hora de inicio *"
                                >
                                    <input
                                        {...bind("fecha_inicio")}
                                        type="datetime-local"
                                        required
                                    />
                                </Field>
                                <Field
                                    name="fecha_fin"
                                    label="Fecha y hora de cierre"
                                >
                                    <input
                                        {...bind("fecha_fin")}
                                        ref={end}
                                        min={v.fecha_inicio || undefined}
                                        type="datetime-local"
                                    />
                                </Field>
                                <Field name="lugar" label="Lugar">
                                    <input
                                        {...bind("lugar")}
                                        maxLength="255"
                                        placeholder="Campus, auditorio o modalidad"
                                    />
                                </Field>
                                {editing &&
                                    select(
                                        "estado",
                                        "Estado *",
                                        Object.entries({
                                            programado: "Programado",
                                            en_vivo: "En vivo",
                                            finalizado: "Finalizado",
                                            cancelado: "Cancelado",
                                        }).map(([id, nombre]) => ({
                                            id,
                                            nombre,
                                        })),
                                        true,
                                    )}
                                <Field
                                    wide
                                    name="descripcion"
                                    label="Descripción"
                                >
                                    <textarea
                                        {...bind("descripcion")}
                                        rows="5"
                                        maxLength="20000"
                                        placeholder="¿Qué compartiremos con nuestra comunidad?"
                                    />
                                </Field>
                                <Field wide name="visibilidad" label="¿Quién puede ver este evento?">
                                    <select {...bind("visibilidad")}>
                                        {(visibilities || []).map((o) => (
                                            <option key={o.id} value={o.id}>{o.nombre}</option>
                                        ))}
                                    </select>
                                    <small>Público: cualquier persona y el canal 24/7. Interno: solo cuentas con sesión. Restringido: solo administradores y operadores. Aplica al directo y a la grabación.</small>
                                </Field>
                                <Field wide name="licencia" label="Licencia de uso del video">
                                    <select {...bind("licencia")}>
                                        {(licenses || []).map((o) => (
                                            <option key={o.id} value={o.id}>{o.nombre}</option>
                                        ))}
                                    </select>
                                    <small className="uebtv-license-help" aria-live="polite">
                                        {(licenses.find((o) => o.id === (v.licencia || "institucional")) || {}).ayuda}
                                    </small>
                                    <details className="uebtv-license-guide">
                                        <summary>¿Qué significa cada licencia?</summary>
                                        <dl>
                                            {(licenses || []).map((o) => (
                                                <div key={o.id}><dt>{o.nombre}</dt><dd>{o.ayuda}</dd></div>
                                            ))}
                                        </dl>
                                        <p>La licencia la decide la autoridad o unidad que organiza el evento; aquí solo se registra. El público la verá en la ficha del video.</p>
                                    </details>
                                </Field>
                                <Field wide name="autorizacion_referencia" label={v.licencia === "otra" ? "Referencia de autorización (obligatoria)" : "Referencia de autorización (opcional)"}>
                                    <input {...bind("autorizacion_referencia")} maxLength="255" required={v.licencia === "otra"} placeholder="Ej.: Oficio N.º 123-FACAGEI-2026 o acta de autorización del 10/11/2026" />
                                    <small>Documento que respalda la difusión. Solo lo ven administradores y operadores.</small>
                                </Field>
                                <div className="uebtv-form-field uebtv-form-field--wide">
                                    <label id="requiere_aprobacion_titulo">Publicación de la grabación</label>
                                    <input type="hidden" name="requiere_aprobacion" value="0" />
                                    <label className="uebtv-event-chat-check" htmlFor="requiere_aprobacion"><input id="requiere_aprobacion" type="checkbox" name="requiere_aprobacion" value="1" defaultChecked={Boolean(Number(v.requiere_aprobacion)) || v.requiere_aprobacion === true} /> Revisar la grabación antes de publicarla</label>
                                    <small>Si no la marcas, la grabación se publica automáticamente al terminar. Un administrador siempre puede retirarla.</small>
                                </div>
                                {canToggleChat && <div className="uebtv-form-field uebtv-form-field--wide">
                                    <label id="chat_enabled_titulo">Comentarios durante el directo</label>
                                    <input type="hidden" name="chat_enabled" value="0" />
                                    <label className="uebtv-event-chat-check" htmlFor="chat_enabled"><input id="chat_enabled" type="checkbox" name="chat_enabled" value="1" defaultChecked={Boolean(Number(v.chat_enabled)) || v.chat_enabled === true} /> Permitir que la comunidad comente</label>
                                    <small>También puedes cambiarlo durante la transmisión desde el detalle del evento.</small>
                                </div>}
                            </div>
                        </section>
                        <section className="uebtv-form-section">
                            <div className="uebtv-form-section__title">
                                <span>02</span>
                                <div>
                                    <h2>Imagen y video</h2>
                                    <p>Una portada que invite a participar.</p>
                                </div>
                            </div>
                            <div className="uebtv-form-fields">
                                <Field
                                    wide
                                    name="miniatura"
                                    label="Miniatura del evento"
                                >
                                    <input
                                        id="miniatura"
                                        name="miniatura"
                                        type="file"
                                        accept="image/jpeg,image/png,image/webp"
                                        onChange={(e) => {
                                            const f = e.target.files[0],
                                                valid =
                                                    !f ||
                                                    ([
                                                        "image/jpeg",
                                                        "image/png",
                                                        "image/webp",
                                                    ].includes(f.type) &&
                                                        f.size <= 2097152);
                                            e.target.setCustomValidity(
                                                valid
                                                    ? ""
                                                    : "Selecciona JPG, PNG o WebP de hasta 2 MB.",
                                            );
                                            setFile(valid ? f : null);
                                        }}
                                    />
                                    <small>
                                        JPG, PNG o WebP · Hasta 2 MB y 4096 ×
                                        4096 píxeles.
                                    </small>
                                </Field>
                                <Field
                                    wide
                                    name="video_url"
                                    label="Enlace alternativo del video grabado"
                                >
                                    <input
                                        {...bind("video_url")}
                                        type="url"
                                        maxLength="255"
                                        placeholder="https://servidor.com/video.mp4"
                                    />
                                    <small>
                                        Opcional. Se utiliza cuando no hay una
                                        grabación automática.
                                    </small>
                                </Field>
                                <Field wide name="recording_fps_limit" label="FPS máximos de la grabación automática">
                                    <select {...bind("recording_fps_limit")} disabled={recordingSettingsLocked}>
                                        <option value="">Conservar FPS originales de OBS</option>
                                        <option value="30">Máximo 30 FPS · menos almacenamiento</option>
                                        <option value="60">Máximo 60 FPS</option>
                                    </select>
                                    <small>{recordingSettingsLocked ? "No se puede cambiar después de iniciar la transmisión." : "Solo afecta al MP4 archivado; no cambia el directo ni aumenta los FPS de origen."}</small>
                                </Field>
                            </div>
                        </section>
                    </div>
                    <aside
                        className="event-editor-sidebar"
                        aria-label="Vista previa del evento"
                    >
                        <div className="event-preview">
                            <div className="event-preview-cover">
                                <span className="event-preview-brand">
                                    UEBTV
                                </span>
                                {preview && (
                                    <img
                                        src={preview}
                                        alt="Portada del evento"
                                    />
                                )}
                                <span className="event-preview-label">
                                    VISTA PREVIA
                                </span>
                            </div>
                            <div className="event-preview-body">
                                <span className="event-preview-category">
                                    {categories.find(
                                        (c) =>
                                            String(c.id) ===
                                            String(v.categoria_id),
                                    )?.nombre || "Tu categoría"}
                                </span>
                                <h2>
                                    {v.titulo ||
                                        "El próximo gran momento comienza aquí"}
                                </h2>
                                <p>
                                    {v.fecha_inicio?.replace("T", " · ") ||
                                        "Fecha y hora por definir"}
                                </p>
                                <div className="event-preview-faculty">
                                    {faculties.find(
                                        (f) =>
                                            String(f.id) ===
                                            String(v.facultad_id),
                                    )?.nombre || "Toda la institución"}
                                </div>
                            </div>
                        </div>
                        <div className="event-editor-note">
                            <h2>Tu comunidad te espera</h2>
                            <p>
                                {editing
                                    ? "Los cambios aparecerán en el catálogo y en la sala pública."
                                    : "Al guardar tendrás una sala pública y una clave única para conectar OBS."}
                            </p>
                        </div>
                    </aside>
                    <div className="uebtv-form-actions">
                        <a href={back}>Cancelar</a>
                        <button disabled={busy || !categories.length}>
                            {busy
                                ? "Guardando…"
                                : editing
                                  ? "Guardar cambios"
                                  : "Guardar y generar clave OBS"}{" "}
                            ↗
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}
