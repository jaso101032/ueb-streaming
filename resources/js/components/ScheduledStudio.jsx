import { durationLabel } from "../platform/Channel";
import { Action } from "../platform/shared";
import UebtvMark from "../platform/UebtvMark";
import React, { useEffect, useState } from "react";

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
        arrow: <path d="M5 12h14m-5-5 5 5-5 5" />,
        check: <path d="m5 12 4 4L19 6" />,
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
    failed: "Error de conversión",
    cancelled: "Cancelado",
    paused: "Pausado por directo",
};

function InstitutionalAds({ action, csrf, ads, old, uploadLimits }) {
    const [title, setTitle] = useState(old.ad_title || "");
    const [fileError, setFileError] = useState("");
    const [submitting, setSubmitting] = useState(false);
    const [uploadProgress, setUploadProgress] = useState(0);
    const [submitError, setSubmitError] = useState("");
    const [eventStates, setEventStates] = useState(() => Object.fromEntries(events.map((event) => [event.id, event.state])));
    useEffect(() => {
        const reset = () => setSubmitting(false);
        window.addEventListener("pageshow", reset);
        return () => window.removeEventListener("pageshow", reset);
    }, []);
    useEffect(() => {
        let stopped = false, timer;
        const poll = async () => {
            const pending = events.filter((event) => ["pending", "processing"].includes(eventStates[event.id]));
            if (!pending.length) return;
            const results = await Promise.all(pending.map(async (event) => {
                try {
                    const response = await fetch(event.stateUrl, { cache: "no-store", headers: { Accept: "application/json" } });
                    if (!response.ok) return null;
                    return { id: event.id, state: (await response.json()).state };
                } catch { return null; }
            }));
            if (stopped) return;
            const completed = results.filter((item) => item && item.state !== "processing");
            if (completed.length) {
                setEventStates((current) => ({ ...current, ...Object.fromEntries(completed.map(({ id, state }) => [id, state])) }));
                if (!title.trim() && !date && !file) window.setTimeout(() => window.location.reload(), 800);
            }
            timer = window.setTimeout(poll, 3000);
        };
        timer = window.setTimeout(poll, 2000);
        return () => { stopped = true; window.clearTimeout(timer); };
    }, [events, eventStates, title, date, file]);
    const submitScheduled = (event) => {
        event.preventDefault();
        if (submitting) return;
        setSubmitting(true);
        setUploadProgress(0);
        setSubmitError("");
        const request = new XMLHttpRequest();
        request.open("POST", action);
        request.upload.addEventListener("progress", (progress) => {
            if (!progress.lengthComputable) return;
            const percent = Math.round((progress.loaded / progress.total) * 100);
            setUploadProgress(percent);
        });
        request.addEventListener("load", () => {
            if (request.status >= 500) {
                setSubmitError("El servidor no pudo procesar la carga. Inténtalo de nuevo en un momento.");
                setSubmitting(false);
                return;
            }
            window.location.assign(request.responseURL || action);
        });
        request.addEventListener("error", () => {
            setSubmitError("Se interrumpió la subida. Revisa tu conexión e inténtalo de nuevo.");
            setSubmitting(false);
        });
        request.send(new FormData(event.currentTarget));
    };
    const choose = (event) => {
        const file = event.target.files[0];
        const error = file && (!/\.(mp4|m4v|mov|mkv|webm|avi|wmv|mpeg|mpg)$/i.test(file.name)
            ? "Selecciona MP4, MOV, MKV, WebM, AVI, WMV o MPEG."
            : file.size > uploadLimits.maxBytes ? `El video supera el límite de ${uploadLimits.maxMb} MB.` : "");
        event.target.setCustomValidity(error || "");
        setFileError(error || "");
    };
    return <section id="schedule-ads" className="ad-studio" aria-labelledby="ad-studio-title">
        <div className="ad-studio-heading">
            <div><p className="studio-eyebrow">CONTINUIDAD DE LA SEÑAL</p><h2 id="ad-studio-title">Publicidad institucional</h2>
                <p>Estos videos cubren los huecos de la señal continua. Un directo o un programa siempre tiene prioridad. La audiencia verá una «P» y no podrá adelantarlos.</p></div>
            <span className="schedule-pill">{ads.filter((ad) => ad.active && ad.status === "ready").length} anuncios activos</span>
        </div>
        <div className="ad-studio-grid">
            <form className="schedule-form ad-studio-form" method="POST" action={action} encType="multipart/form-data" onSubmit={() => setSubmitting(true)} aria-busy={submitting}>
                <input type="hidden" name="_token" value={csrf} />
                <Section number="P" title="Añadir anuncio oficial" subtitle="Solo utiliza piezas institucionales autorizadas por la UEB.">
                    <Field id="ad-title" label="Nombre del anuncio">
                        <input id="ad-title" name="ad_title" maxLength={255} required value={title} onChange={(e) => setTitle(e.target.value)} placeholder="Ej. Campaña institucional UEB" />
                    </Field>
                    <Field id="ad-video" label="Archivo de video">
                        <input id="ad-video" name="ad_video" type="file" accept=".mp4,.m4v,.mov,.mkv,.webm,.avi,.wmv,.mpeg,.mpg" required onChange={choose} aria-describedby="ad-file-help ad-file-error" />
                    </Field>
                    <p id="ad-file-help" className="schedule-help">Hasta {uploadLimits.maxMb} MB. El archivo se optimiza antes de emitirse y se repetirá con los otros anuncios durante los huecos.</p>
                    {fileError && <p id="ad-file-error" className="schedule-file-error" role="alert">{fileError}</p>}
                </Section>
                <div className="schedule-submit"><button type="submit" className="studio-button" disabled={submitting}>{submitting ? "Subiendo…" : "Subir publicidad"}<Icon name="upload" size={17} /></button></div>
            </form>
            <div className="studio-panel ad-studio-list">
                <div className="studio-panel-heading"><div><p className="studio-eyebrow">BIBLIOTECA INSTITUCIONAL</p><h3>Anuncios disponibles</h3></div><strong>{ads.length}</strong></div>
                {ads.length ? ads.map((ad) => <article className="ad-studio-item" key={ad.id}>
                    <div className="ad-studio-item__mark" aria-hidden="true">P</div>
                    <div className="ad-studio-item__body"><h4>{ad.title}</h4><p>{stateLabels[ad.status] || ad.status}{ad.status === "ready" ? ` · ${durationLabel(ad.duration)}` : ""}{ad.status === "ready" && !ad.active ? " · Fuera del aire" : ""}</p>{ad.error && <small role="status">{ad.error}</small>}</div>
                    <div className="ad-studio-item__actions">
                        {ad.status === "failed" && <Action url={ad.retryUrl} className="studio-button">Reintentar</Action>}
                        <Action url={ad.toggleUrl} className="studio-button" confirm={ad.active ? "¿Quitar este anuncio de la señal continua?" : undefined}>{ad.active ? "Desactivar" : "Activar"}</Action>
                    </div>
                </article>) : <div className="schedule-empty"><h4>Aún no hay anuncios</h4><p>Mientras no haya programación ni directo, la señal mostrará la pantalla de espera.</p></div>}
            </div>
        </div>
    </section>;
}

export default function ScheduledStudio({
    action,
    adAction,
    ads = [],
    csrf,
    timezone,
    categories,
    events,
    pagination,
    old,
    errors,
    success,
    summary,
    channelUrl,
    uploadLimits,
}) {
    const [title, setTitle] = useState(old.titulo || "");
    const [date, setDate] = useState(old.fecha_inicio || "");
    const [category, setCategory] = useState(old.categoria_id || "");
    const [file, setFile] = useState(null);
    const [preview, setPreview] = useState("");
    const [duration, setDuration] = useState(0);
    const [fileError, setFileError] = useState("");
    const [submitting, setSubmitting] = useState(false);
    useEffect(() => {
        if (!file) {
            setPreview("");
            return;
        }
        const url = URL.createObjectURL(file);
        setPreview(url);
        return () => URL.revokeObjectURL(url);
    }, [file]);
    useEffect(() => {
        const reset = () => setSubmitting(false);
        window.addEventListener("pageshow", reset);
        return () => window.removeEventListener("pageshow", reset);
    }, []);
    const choose = (event) => {
        const selected = event.target.files[0];
        setDuration(0);
        const error =
            selected &&
            (!/\.(mp4|m4v|mov|mkv|webm|avi|wmv|mpeg|mpg)$/i.test(selected.name)
                ? "Selecciona MP4, MOV, MKV, WebM, AVI, WMV o MPEG."
                : selected.size > uploadLimits.maxBytes
                  ? `El video supera el límite de ${uploadLimits.maxMb} MB de este servidor.`
                  : "");
        event.target.setCustomValidity(error || "");
        setFileError(error || "");
        setFile(error ? null : selected || null);
    };
    const complete = [!!title.trim() && !!category, !!date, !!file].filter(
        Boolean,
    ).length;
    const coverageEnd = summary.endsAt
        ? new Date(summary.endsAt).toLocaleString("es-EC", { dateStyle: "short", timeStyle: "short" })
        : null;
    return (
        <div className="studio-page schedule-page schedule-react">
            <div className="studio-heading">
                <div>
                    <p className="studio-eyebrow">TU SEÑAL, A TU TIEMPO</p>
                    <h1>
                        Contenido programado
                        <span className="studio-heading-dot">.</span>
                    </h1>
                    <p>
                        Prepara tu contenido hoy. Compártelo con todos en el
                        momento indicado.
                    </p>
                </div>
                <span className="schedule-pill">
                    <Icon name="clock" size={14} /> Emisiones pregrabadas
                </span>
            </div>
            <nav className="schedule-content-switch" aria-label="Tipos de contenido">
                <a href="#schedule-program-form"><Icon name="clock" size={17} /> Programación con horario</a>
                <a href="#schedule-ads"><span aria-hidden="true">P</span> Publicidad institucional</a>
            </nav>
            {success && (
                <p className="studio-alert" role="status">
                    {success}
                </p>
            )}
            {errors.length > 0 && (
                <div className="uebtv-form-errors" role="alert">
                    {errors.map((error, i) => (
                        <p key={i}>{error}</p>
                    ))}
                </div>
            )}
            <div
                className="schedule-totals"
                aria-label="Duración de la programación"
            >
                <article>
                    <small>CONTENIDO LISTO</small>
                    <strong>{durationLabel(summary.totalSeconds)}</strong>
                    <p>
                        {summary.count} videos disponibles, de todas las
                        páginas.
                    </p>
                </article>
                <article>
                    <small>POR EMITIR</small>
                    <strong>{durationLabel(summary.remainingSeconds)}</strong>
                    <p>
                        Tiempo de video pendiente; no incluye directos ni
                        esperas.
                    </p>
                </article>
                <article>
                    <small>COBERTURA PREVISTA</small>
                    <strong>{coverageEnd || "Sin contenido listo"}</strong>
                    <p>{coverageEnd ? "La programación llega hasta esta hora; un directo puede desplazarla." : "Sube contenido para calcular la cobertura del canal."}</p>
                </article>
            </div>
            <div
                className="schedule-progress"
                aria-label={`${complete} de 3 secciones completadas`}
            >
                <span>Prepara tu emisión</span>
                <div>
                    {["Contenido", "Horario", "Video"].map((label, i) => (
                        <span
                            key={label}
                            className={
                                [!!title.trim() && !!category, !!date, !!file][
                                    i
                                ]
                                    ? "is-complete"
                                    : ""
                            }
                        >
                            <i>
                                {[!!title.trim() && !!category, !!date, !!file][
                                    i
                                ] ? (
                                    <Icon name="check" size={13} />
                                ) : (
                                    i + 1
                                )}
                            </i>
                            {label}
                        </span>
                    ))}
                </div>
                <small>{complete} / 3</small>
            </div>
            {uploadLimits.serverLimited && (
                <p className="studio-alert" role="status">
                    El servidor permite actualmente {uploadLimits.maxMb} MB por
                    video. Para cargar hasta 500 MB, amplía upload_max_filesize
                    y post_max_size y reinicia el servidor PHP.
                </p>
            )}
            <div className="schedule-layout">
                <form
                    id="schedule-program-form"
                    className="schedule-form"
                    method="POST"
                    action={action}
                    encType="multipart/form-data"
                    onSubmit={submitScheduled}
                    aria-busy={submitting}
                >
                    <input type="hidden" name="_token" value={csrf} />
                    <Section
                        number="01"
                        title="Dale identidad a tu emisión"
                        subtitle="Así conocerá tu contenido la comunidad."
                    >
                        <Field id="title" label="Título de la emisión">
                            <input
                                id="title"
                                name="titulo"
                                required
                                maxLength={255}
                                value={title}
                                onChange={(e) => setTitle(e.target.value)}
                                placeholder="Un título que invite a descubrir"
                            />
                        </Field>
                        <Field id="description" label="Descripción" optional>
                            <textarea
                                id="description"
                                name="descripcion"
                                rows={4}
                                maxLength={20000}
                                defaultValue={old.descripcion || ""}
                                placeholder="Cuéntale a tu audiencia qué va a encontrar…"
                            />
                        </Field>
                        <Field id="category" label="Categoría">
                            <select
                                id="category"
                                name="categoria_id"
                                required
                                value={category}
                                onChange={(e) => setCategory(e.target.value)}
                            >
                                <option value="">
                                    Selecciona una categoría
                                </option>
                                {categories.map((c) => (
                                    <option key={c.id} value={c.id}>
                                        {c.nombre}
                                    </option>
                                ))}
                            </select>
                        </Field>
                    </Section>
                    <Section
                        number="02"
                        title="Elige el momento"
                        subtitle="Hora mínima de inicio. Si hay otra emisión, el video espera su turno."
                    >
                        <Field id="start" label="Fecha y hora de inicio">
                            <input
                                id="start"
                                type="datetime-local"
                                name="fecha_inicio"
                                required
                                value={date}
                                onChange={(e) => setDate(e.target.value)}
                                aria-describedby="schedule-zone"
                            />
                            <small id="schedule-zone" className="schedule-zone">
                                Zona horaria: <strong>{timezone}</strong>.
                                Introduce la hora en esta zona.
                            </small>
                        </Field>
                    </Section>
                    <Section
                        number="03"
                        title="Agrega tu video"
                        subtitle="Convertiremos tu archivo a un video optimizado para la web."
                    >
                        <div
                            className={`schedule-upload ${file ? "has-file" : ""}`}
                        >
                            <span className="schedule-upload-icon">
                                <Icon
                                    name={file ? "check" : "upload"}
                                    size={27}
                                />
                            </span>
                            <label htmlFor="video">
                                {file
                                    ? "Tu video está seleccionado"
                                    : "Selecciona el video de tu próxima emisión"}
                            </label>
                            <p>
                                MP4, MOV, MKV, WebM, AVI, WMV, MPEG · Hasta{" "}
                                {uploadLimits.maxMb} MB
                            </p>
                            <input
                                id="video"
                                type="file"
                                name="video"
                                accept=".mp4,.m4v,.mov,.mkv,.webm,.avi,.wmv,.mpeg,.mpg"
                                required
                                aria-describedby="upload-help file-error"
                                onChange={choose}
                            />
                            {file && (
                                <p className="schedule-file" role="status">
                                    <span>{file.name}</span>
                                    <strong>
                                        {(file.size / 1048576).toFixed(1)} MB
                                    </strong>
                                </p>
                            )}
                        </div>
                        <p
                            id="file-error"
                            role="alert"
                            className="schedule-file-error"
                        >
                            {fileError}
                        </p>
                        <p id="upload-help" className="schedule-help">
                            El tamaño permitido también depende del servidor.
                            Mantén esta página abierta hasta que termine la
                            subida.
                        </p>
                    </Section>
                    <div className="schedule-submit">
                        <p role="status">
                            {submitting ? (
                                uploadProgress < 100
                                    ? `Subiendo video al servidor: ${uploadProgress}%. No cierres esta página…`
                                    : "Subida completa; verificando el video y registrando la programación…"
                            ) : submitError ? (
                                submitError
                            ) : (
                                <>
                                    Tu contenido se mostrará como{" "}
                                    <strong>pregrabado</strong>.
                                </>
                            )}
                        </p>
                        <button
                            className="studio-button"
                            type="submit"
                            disabled={submitting}
                        >
                            {submitting
                                ? "Procesando…"
                                : "Subir y programar emisión"}
                            <Icon name="arrow" size={17} />
                        </button>
                        {submitting && <progress className="schedule-upload-progress" max="100" value={uploadProgress} aria-label="Progreso de subida" />}
                    </div>
                </form>
                <aside
                    className="schedule-preview"
                    aria-label="Vista previa de la programación"
                >
                    {preview ? (
                        <div className="schedule-local-preview">
                            <video
                                key={preview}
                                src={preview}
                                controls
                                preload="metadata"
                                aria-label="Vista previa del video seleccionado"
                                onLoadedMetadata={(e) =>
                                    setDuration(
                                        Number.isFinite(
                                            e.currentTarget.duration,
                                        )
                                            ? Math.ceil(
                                                  e.currentTarget.duration,
                                              )
                                            : 0,
                                    )
                                }
                            />
                            <span>VISTA PREVIA LOCAL</span>
                            <p className="schedule-help">
                                Si el navegador no muestra este formato, podrás
                                reproducirlo después de convertirlo.
                            </p>
                        </div>
                    ) : (
                        <div className="schedule-preview-art">
                            <UebtvMark className="uebtv-brand-logo--schedule" />
                            <span className="schedule-preview-play">
                                <Icon />
                            </span>
                            <span className="schedule-preview-tag">
                                PREGRABADO · SINCRONIZADO
                            </span>
                        </div>
                    )}
                    <div className="schedule-preview-body">
                        <p className="studio-eyebrow">TU PRÓXIMA EMISIÓN</p>
                        <h2>
                            {title.trim() || "Una nueva historia por compartir"}
                        </h2>
                        {category && (
                            <span className="schedule-category">
                                {
                                    categories.find(
                                        (c) =>
                                            String(c.id) === String(category),
                                    )?.nombre
                                }
                            </span>
                        )}
                        <p className="schedule-preview-date">
                            <Icon name="clock" size={15} />
                            <span>
                                {date
                                    ? date.replace("T", " · ")
                                    : "Fecha y hora por definir"}
                            </span>
                        </p>
                        <small>{timezone}</small>
                        {duration > 0 && (
                            <p className="schedule-help">
                                Duración estimada:{" "}
                                <strong>{durationLabel(duration)}</strong>. Se
                                verifica al guardar.
                            </p>
                        )}
                    </div>
                    <div className="schedule-guide">
                        <h3>Así lo verá tu audiencia</h3>
                        <ol>
                            {[
                                [
                                    "Antes del estreno",
                                    "La sala anuncia la fecha de inicio.",
                                ],
                                [
                                    "El directo tiene prioridad",
                                    "El video se pausa y continúa desde ese punto al terminar el directo.",
                                ],
                                [
                                    "Disponible después",
                                    "Al finalizar, se puede ver desde el inicio.",
                                ],
                            ].map(([heading, text], i) => (
                                <li key={heading}>
                                    <span>{i + 1}</span>
                                    <div>
                                        <strong>{heading}</strong>
                                        <p>{text}</p>
                                    </div>
                                </li>
                            ))}
                        </ol>
                    </div>
                </aside>
            </div>
            <section className="studio-panel schedule-list">
                <div className="studio-panel-heading">
                    <div>
                        <p className="studio-eyebrow">TU CARTELERA</p>
                        <h2>Emisiones programadas</h2>
                        <a className="studio-text-link" href={action}>
                            Actualizar estado ↻
                        </a>
                    </div>
                    <span className="schedule-count">
                        {pagination.total} emisiones
                    </span>
                </div>
                {events.length ? (
                        events.map((event) => (
                            (() => { const state = eventStates[event.id] || event.state; return (
                        <div className="schedule-event" key={event.id}>
                            <div className="schedule-date">
                                <strong>{event.day}</strong>
                                <span>{event.month}</span>
                            </div>
                            <div className="studio-event-info">
                                <h3>{event.title}</h3>
                                <p>
                                    {event.time} · {event.minutes} min ·
                                    Pregrabado
                                </p>
                                {event.estimatedStart && (
                                    <small>
                                        Inicio estimado:{" "}
                                        {new Date(
                                            event.estimatedStart,
                                        ).toLocaleString("es-EC")}
                                    </small>
                                )}
                            </div>
                            {event.optimizedBytes && (
                                <small>
                                    {(event.optimizedBytes / 1048576).toFixed(
                                        1,
                                    )}{" "}
                                    MB optimizado
                                    {event.sourceBytes
                                        ? ` · Original ${(event.sourceBytes / 1048576).toFixed(1)} MB`
                                        : ""}
                                </small>
                            )}
                            {event.error && <p role="status">{event.error}</p>}
                            {state === "failed" && (
                                <Action url={event.retryUrl}>Reintentar</Action>
                            )}
                            <span className="schedule-pill">
                                {stateLabels[state]}
                            </span>
                            <a className="studio-text-link" href={event.url}>
                                Abrir sala ↗
                            </a>
                        </div>
                            ); })()
                    ))
                ) : (
                    <div className="schedule-empty">
                        <span>
                            <Icon name="clock" size={26} />
                        </span>
                        <h3>Tu próxima emisión empieza aquí</h3>
                        <p>
                            Sube tu primer video y reserva un momento para
                            conectar con la comunidad.
                        </p>
                    </div>
                )}
                {pagination.last > 1 && (
                    <nav
                        className="schedule-pagination"
                        aria-label="Páginas de emisiones"
                    >
                        {pagination.previous && (
                            <a href={pagination.previous}>← Anterior</a>
                        )}
                        <span>
                            Página {pagination.current} de {pagination.last}
                        </span>
                        {pagination.next && (
                            <a href={pagination.next}>Siguiente →</a>
                        )}
                    </nav>
                )}
            </section>
            <InstitutionalAds action={adAction} csrf={csrf} ads={ads} old={old} uploadLimits={uploadLimits} />
        </div>
    );
}
