import { durationLabel } from "../platform/Channel";
import { Action, useShell } from "../platform/shared";
import React, { useEffect, useRef, useState } from "react";

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

function InstitutionalAds({ action, csrf, ads, old, uploadLimits }) {
    const [title, setTitle] = useState(old.ad_title || "");
    const [fileError, setFileError] = useState("");
    const { upload, submit, reset } = useVideoUpload();
    const submitting = upload.phase === "uploading" || upload.phase === "verifying";
    const choose = (event) => {
        const file = event.target.files[0];
        const error = file && (!/\.(mp4|m4v|mov|mkv|webm|avi|wmv|mpeg|mpg)$/i.test(file.name)
            ? "Selecciona MP4, MOV, MKV, WebM, AVI, WMV o MPEG."
            : file.size > uploadLimits.maxBytes ? `El video supera el límite de ${uploadLimits.maxMb} MB.` : "");
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
                    <p id="ad-file-help" className="schedule-help">Hasta {uploadLimits.maxMb} MB. El archivo se optimiza antes de emitirse y se repetirá con los otros anuncios durante los huecos.</p>
                    {fileError && <p id="ad-file-error" className="schedule-file-error" role="alert">{fileError}</p>}
                </Section>
                <div className="schedule-submit"><UploadProgress upload={upload} /><button type="submit" className="studio-button" disabled={submitting}>{submitting ? "Subiendo…" : "Subir publicidad"}<Icon name="upload" size={17} /></button></div>
            </form>
            <div className="studio-panel ad-studio-list">
                <div className="studio-panel-heading"><div><p className="studio-eyebrow">BIBLIOTECA INSTITUCIONAL</p><h3>Anuncios disponibles</h3></div><strong>{ads.length}</strong></div>
                {ads.length ? ads.map((ad) => <article className="ad-studio-item" key={ad.id}>
                    <div className="ad-studio-item__mark" aria-hidden="true">P</div>
                    <div className="ad-studio-item__body"><h4>{ad.title}</h4><p>{stateLabels[ad.status] || ad.status}{ad.status === "ready" ? ` · ${durationLabel(ad.duration)}` : ""}{ad.status === "ready" && !ad.active ? " · Fuera del aire" : ""}</p><ConversionProgress status={ad.status} percent={ad.progress} />{ad.error && <small role="status">{ad.error}</small>}</div>
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
    progressUrl,
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
    const shell = useShell();
    const [title, setTitle] = useState(old.titulo || "");
    const [date, setDate] = useState(old.fecha_inicio || "");
    const [category, setCategory] = useState(old.categoria_id || "");
    const [file, setFile] = useState(null);
    const [preview, setPreview] = useState("");
    const [thumbnail, setThumbnail] = useState(null);
    const [thumbnailPreview, setThumbnailPreview] = useState("");
    const [thumbnailError, setThumbnailError] = useState("");
    const thumbnailInput = useRef(null);
    const [duration, setDuration] = useState(0);
    const [fileError, setFileError] = useState("");
    const { upload, submit, reset } = useVideoUpload();
    const submitting = upload.phase === "uploading" || upload.phase === "verifying";
    const [eventRows, setEventRows] = useState(events);
    const [adRows, setAdRows] = useState(ads);
    const hasConversions = eventRows.some((event) => ["pending", "processing"].includes(event.state))
        || adRows.some((ad) => ["pending", "processing"].includes(ad.status));
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
        if (!thumbnail) {
            setThumbnailPreview("");
            return;
        }
        const url = URL.createObjectURL(thumbnail);
        setThumbnailPreview(url);
        return () => URL.revokeObjectURL(url);
    }, [thumbnail]);
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
                    const state = current.status === "ready"
                        ? (["pending", "processing", "failed"].includes(event.state) ? "ready" : event.state)
                        : current.status;
                    return { ...event, state, progress: current.progress, error: current.error };
                }));
                setAdRows((rows) => rows.map((ad) => data.ads?.[ad.id]
                    ? { ...ad, status: data.ads[ad.id].status, progress: data.ads[ad.id].progress, error: data.ads[ad.id].error }
                    : ad));
            } catch (error) { if (error.name !== "AbortError") { /* Retry on next poll. */ } }
        };
        refresh();
        const timer = window.setInterval(refresh, 5000);
        return () => { controller.abort(); window.clearInterval(timer); };
    }, [hasConversions, progressUrl]);
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
        reset();
    };
    const complete = [!!title.trim() && !!category, !!date, !!file].filter(
        Boolean,
    ).length;
    const chooseThumbnail = (event) => {
        const selected = event.target.files[0];
        const error = selected && (!/\.(jpe?g|png|webp)$/i.test(selected.name)
            ? "Selecciona una imagen JPG, PNG o WebP."
            : selected.size > 2 * 1024 * 1024
              ? "La imagen de portada no puede superar los 2 MB."
              : "");
        event.target.setCustomValidity(error || "");
        setThumbnailError(error || "");
        setThumbnail(error ? null : selected || null);
    };
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
                    onSubmit={submit}
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
                        <div className="schedule-thumbnail-field">
                            <label htmlFor="schedule-thumbnail">Imagen de portada del catálogo <span>Opcional</span></label>
                            <p>Esta imagen aparecerá en la tarjeta del video, como portada de los eventos en vivo.</p>
                            <input
                                ref={thumbnailInput}
                                id="schedule-thumbnail"
                                type="file"
                                name="miniatura"
                                accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                                onChange={chooseThumbnail}
                                aria-describedby="schedule-thumbnail-help schedule-thumbnail-error"
                            />
                            <small id="schedule-thumbnail-help">JPG, PNG o WebP · Máximo 2 MB</small>
                            {thumbnail && <div className="schedule-thumbnail-selected" role="status">
                                <img src={thumbnailPreview} alt="Vista previa de la portada elegida" />
                                <span>{thumbnail.name}</span>
                                <button type="button" onClick={() => {
                                    setThumbnail(null);
                                    setThumbnailError("");
                                    if (thumbnailInput.current) {
                                        thumbnailInput.current.value = "";
                                        thumbnailInput.current.setCustomValidity("");
                                    }
                                }}>Quitar</button>
                            </div>}
                            <p id="schedule-thumbnail-error" className="schedule-file-error" role="alert">{thumbnailError}</p>
                        </div>
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
                                "No cierres esta página hasta completar la subida."
                            ) : (
                                <>
                                    Tu contenido se mostrará como{" "}
                                    <strong>pregrabado</strong>.
                                </>
                            )}
                        </p>
                        <UploadProgress upload={upload} />
                        <button
                            className="studio-button"
                            type="submit"
                            disabled={submitting}
                        >
                            {submitting
                                ? "Subiendo…"
                                : "Subir y programar emisión"}
                            <Icon name="arrow" size={17} />
                        </button>
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
                        <div className={`schedule-preview-art ${thumbnailPreview ? "has-cover" : ""}`}>
                            {thumbnailPreview ? <img className="schedule-preview-cover" src={thumbnailPreview} alt="" aria-hidden="true" /> : <img
                                className="schedule-preview-logo"
                                src={shell.logo}
                                alt="Universidad Estatal de Bolívar"
                                width="190"
                                height="40"
                            />}
                            <span className="schedule-preview-play">
                                <Icon />
                            </span>
                            <span className="schedule-preview-tag">
                                {thumbnailPreview ? "PORTADA DEL CATÁLOGO" : "PREGRABADO · SINCRONIZADO"}
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
                {eventRows.length ? (
                    eventRows.map((event) => (
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
                            <ConversionProgress status={event.state} percent={event.progress} />
                            {event.state === "failed" && (
                                <Action url={event.retryUrl}>Reintentar</Action>
                            )}
                            <span className="schedule-pill">
                                {stateLabels[event.state]}
                            </span>
                            <a className="studio-text-link" href={event.url}>
                                Abrir sala ↗
                            </a>
                        </div>
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
            <InstitutionalAds action={adAction} csrf={csrf} ads={adRows} old={old} uploadLimits={uploadLimits} />
        </div>
    );
}
