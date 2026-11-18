import React, { useEffect, useLayoutEffect, useRef, useState } from "react";
import { useShell } from "./shared";
export default function Chat({
    endpoint,
    initial,
    canModerate,
    panelRef,
    isRecording,
    canComment = true,
    chatEnabled = true,
    chatRestricted = false,
    chatToggleUrl = null,
    seekable = false,
    playbackSeconds = 0,
    onSeek,
    onDraft,
    viewers = null,
    videoDuration = 0,
}) {
    const { user, login, csrf } = useShell(),
        [messages, setMessages] = useState(isRecording && seekable ? [] : initial),
        [draft, setDraft] = useState(""),
        [error, setError] = useState(""),
        [connection, setConnection] = useState(""),
        [busy, setBusy] = useState(false),
        [removing, setRemoving] = useState(null),
        [enabled, setEnabled] = useState(chatEnabled),
        [restricted, setRestricted] = useState(chatRestricted),
        [unseen, setUnseen] = useState(0),
        [visibleLimit, setVisibleLimit] = useState(80),
        [version, setVersion] = useState(0);
    const replay = isRecording && seekable;
    const commentsOpen = canComment && enabled && !restricted;
    const list = useRef(),
        bottom = useRef(true),
        revision = useRef(0),
        controllers = useRef(new Set());
    const formatPlaybackTime = (seconds) => {
        const value = Math.max(0, Math.floor(Number(seconds) || 0));
        const hours = Math.floor(value / 3600);
        const minutes = Math.floor((value % 3600) / 60);
        const remaining = value % 60;
        return hours
            ? `${hours}:${String(minutes).padStart(2, "0")}:${String(remaining).padStart(2, "0")}`
            : `${minutes}:${String(remaining).padStart(2, "0")}`;
    };
    useEffect(() => () => controllers.current.forEach((c) => c.abort()), []);
    useEffect(() => {
        onDraft?.(draft);
    }, [draft, onDraft]);
    async function request(url, options = {}) {
        const c = new AbortController();
        controllers.current.add(c);
        const t = setTimeout(() => c.abort(), 12000);
        try {
            const r = await fetch(url, {
                ...options,
                signal: c.signal,
                headers: {
                    Accept: "application/json",
                    "X-CSRF-TOKEN": csrf,
                    ...options.headers,
                },
            });
            const body =
                r.status === 304 ? null : await r.json().catch(() => null);
            return { r, body };
        } finally {
            clearTimeout(t);
            controllers.current.delete(c);
        }
    }
    const failure = (r, b) =>
        r.status === 429
            ? "Demasiados intentos. Espera un minuto."
            : [401, 419].includes(r.status)
              ? "Tu sesión venció. Recarga e inicia sesión de nuevo."
              : r.status === 403
                ? "No tienes permiso para esta acción."
                : r.status === 422
                  ? b?.errors?.message?.[0] || "Revisa el mensaje."
                  : "No se pudo completar la operación.";
    useEffect(() => {
        if (!replay) return;
        let stopped = false;
        async function loadReplay() {
            setMessages([]);
            setConnection("Cargando historial de comentarios…");
            let after = 0;
            try {
                do {
                    const { r, body } = await request(`${endpoint}/replay?after_id=${after}`);
                    if (!r.ok || !Array.isArray(body?.messages)) throw Error();
                    if (stopped) return;
                    setMessages((current) => [...current, ...body.messages]);
                    after = body.next_after_id;
                } while (after !== null);
                if (!stopped) setConnection("");
            } catch {
                if (!stopped) setConnection("No se pudo cargar todo el historial. Reintenta la conexión.");
            }
        }
        loadReplay();
        return () => { stopped = true; };
    }, [endpoint, replay, version]);
    useEffect(() => {
        if (replay) return undefined;
        let stopped = false,
            timer,
            etag = null,
            delay = 5000,
            working = false;
        async function refresh() {
            if (stopped || document.hidden || working) return;
            working = true;
            const rev = revision.current;
            try {
                const { r, body } = await request(endpoint, {
                    headers: etag ? { "If-None-Match": etag } : {},
                });
                if (r.status !== 304 && !r.ok) throw Error();
                if (!stopped && rev === revision.current) {
                    setEnabled(r.headers.get("X-Chat-Enabled") !== "0");
                    setRestricted(r.headers.get("X-Chat-Restricted") === "1");
                    if (Array.isArray(body)) {
                        setMessages(body);
                        etag = r.headers.get("ETag");
                    }
                    setConnection("");
                    delay = 5000;
                }
            } catch {
                if (!stopped) {
                    setConnection("Reconectando con la comunidad…");
                    delay = Math.min(delay * 2, 30000);
                }
            } finally {
                working = false;
                if (!stopped) timer = setTimeout(refresh, delay);
            }
        }
        const visible = () => {
            clearTimeout(timer);
            if (!document.hidden) refresh();
        };
        refresh();
        document.addEventListener("visibilitychange", visible);
        return () => {
            stopped = true;
            clearTimeout(timer);
            document.removeEventListener("visibilitychange", visible);
        };
    }, [endpoint, version, replay]);
    const lastCount = useRef(0);
    useLayoutEffect(() => {
        if (bottom.current && list.current)
            list.current.scrollTop = list.current.scrollHeight;
    }, [messages, playbackSeconds]);
    const reachedMessages = replay
        // Los comentarios escritos después del final del video aparecen al terminar.
        ? messages.filter((m) => m.playback_seconds == null || m.playback_seconds <= playbackSeconds
            || (videoDuration > 0 && m.playback_seconds > videoDuration && playbackSeconds >= Math.floor(videoDuration) - 1))
        : messages;
    const visibleMessages = replay ? reachedMessages.slice(-visibleLimit) : reachedMessages;
    const earlierCount = reachedMessages.length - visibleMessages.length;
    async function send(e) {
        e.preventDefault();
        if (busy) return;
        if (!draft.trim()) {
            setError("Escribe un mensaje.");
            return;
        }
        const sent = draft;
        setBusy(true);
        setError("");
        try {
            const { r, body } = await request(endpoint, {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ message: sent.trim() }),
            });
            if (!r.ok) {
                setError(failure(r, body));
                return;
            }
            setDraft((x) => (x === sent ? "" : x));

            revision.current++;
            setVersion((x) => x + 1);
            bottom.current = true;
        } catch {
            setError(
                "No se confirmó el envío. Conservamos tu texto; revisa el chat antes de reintentar.",
            );
        } finally {
            setBusy(false);
        }
    }
    async function remove(m) {
        if (!window.confirm("¿Retirar este mensaje para todos?")) return;
        setRemoving(m.id);
        setError("");
        try {
            const { r, body } = await request(`${endpoint}/${m.id}`, {
                method: "DELETE",
            });
            if (!r.ok) {
                setError(failure(r, body));
                return;
            }
            revision.current++;
            setMessages((x) => x.filter((v) => v.id !== m.id));
            setVersion((x) => x + 1);
        } catch {
            setError("No se pudo retirar el mensaje. Inténtalo nuevamente.");
        } finally {
            setRemoving(null);
        }
    }
    // "Nuevos mensajes ↓" cuando llegan mensajes mientras se lee más arriba.
    useEffect(() => {
        const added = visibleMessages.length - lastCount.current;
        lastCount.current = visibleMessages.length;
        if (added > 0 && !bottom.current) setUnseen((count) => count + added);
    }, [visibleMessages.length]);
    const toBottom = () => {
        bottom.current = true; setUnseen(0);
        if (list.current) list.current.scrollTop = list.current.scrollHeight;
    };
    const statusText = commentsOpen ? (isRecording ? "Comentarios abiertos" : "En vivo") : "Solo lectura";
    // El minuto del comentario solo se muestra si lleva a un momento real de la grabación.
    const momentFor = (m) => replay && onSeek && m.playback_seconds != null && videoDuration > 0 && m.playback_seconds <= videoDuration + 1
        ? m.playback_seconds : null;
    return (
        <div className={`wv-chat ${!messages.length && !commentsOpen ? "is-empty" : ""}`} ref={panelRef}>
            <div className="wv-chat__bar">
                <span className={`wv-chat__status ${commentsOpen ? "is-open" : ""}`}><i aria-hidden="true" />{statusText}</span>
                {viewers != null && <span className="wv-chat__viewers" title="Espectadores conectados"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M16 19v-1.3c0-2-1.8-3.7-4-3.7s-4 1.7-4 3.7V19m4-5a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z" /></svg>{viewers}</span>}
                {replay && <span className="wv-chat__hint">Aparecen al llegar a su momento del video</span>}
            </div>
            <div className="wv-chat__body" id="uebtv-chat-content">
                {chatToggleUrl && canComment && <form method="POST" action={chatToggleUrl} className="wv-chat__control">
                    <input type="hidden" name="_token" value={csrf} />
                    <input type="hidden" name="enabled" value={enabled ? "0" : "1"} />
                    <button type="submit">{enabled ? "Desactivar comentarios" : "Activar comentarios"}</button>
                    {canModerate && <small>Puedes retirar mensajes inapropiados.</small>}
                </form>}
                <div
                    ref={list}
                    className="wv-chat__list"
                    role="log"
                    aria-label="Mensajes de la comunidad"
                    onScroll={() => {
                        const l = list.current;
                        bottom.current = l.scrollHeight - l.scrollTop - l.clientHeight < 60;
                        if (bottom.current) setUnseen(0);
                    }}
                >
                    {earlierCount > 0 && <button type="button" className="wv-chat__history" onClick={() => {
                        bottom.current = false;
                        setVisibleLimit((limit) => limit + 80);
                    }}>Ver {Math.min(earlierCount, 80)} comentarios anteriores</button>}
                    {visibleMessages.map((m) => (
                        <div className="wv-msg" key={m.id} style={{ "--hue": userHue(m.user) }}>
                            <span className="wv-msg__avatar" aria-hidden="true">{Array.from(m.user)[0]?.toUpperCase()}</span>
                            <div className="wv-msg__body">
                                <div className="wv-msg__meta">
                                    <strong>{m.user}</strong>
                                    {momentFor(m) != null
                                        ? <button type="button" className="wv-msg__moment" onClick={() => onSeek(m.playback_seconds)} aria-label={`Ir al minuto ${formatPlaybackTime(m.playback_seconds)} del video`} title="Ver este momento del video"><span aria-hidden="true">▶</span>{formatPlaybackTime(m.playback_seconds)}</button>
                                        : m.created_at && <time dateTime={m.created_at} title={new Date(m.created_at).toLocaleString("es-EC")}>{clockTime(m.created_at)}</time>}
                                    {canModerate && <button type="button" className="wv-msg__remove" aria-label={`Retirar mensaje de ${m.user}`} disabled={removing === m.id} onClick={() => remove(m)}>Retirar</button>}
                                </div>
                                <p className="wv-msg__text">{m.message}</p>
                            </div>
                        </div>
                    ))}
                    {!visibleMessages.length && !connection && (
                        <div className="wv-chat__empty">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 6h14v9H9l-4 3z" /></svg>
                            {replay ? "Los comentarios aparecerán al llegar a su momento en el video." : commentsOpen ? "Sé el primero en compartir una idea." : "Todavía no hay comentarios."}
                        </div>
                    )}
                </div>
                {unseen > 0 && <button type="button" className="wv-chat__new" onClick={toBottom}>{unseen === 1 ? "1 mensaje nuevo" : `${unseen} mensajes nuevos`} ↓</button>}
                {connection && <p className="wv-chat__feedback" role="status">{connection}</p>}
                {error && <p id="chatError" className="wv-chat__feedback is-error" role="alert">{error}</p>}
                <div className="wv-chat__footer">
                    {!commentsOpen ? (
                        <p className="wv-chat__closed" role="status">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="10" width="14" height="10" rx="2" /><path d="M8 10V7a4 4 0 0 1 8 0v3" /></svg>
                            <span><strong>{!canComment ? "El chat terminó con la transmisión." : restricted ? "Tu cuenta tiene restringidos los comentarios." : "Los comentarios están desactivados."}</strong> Puedes seguir leyendo.</span>
                        </p>
                    ) : user ? (
                        <form className="wv-chat__form" onSubmit={send}>
                            <input
                                aria-label="Tu mensaje"
                                aria-describedby={error ? "chatError" : undefined}
                                required
                                maxLength={180}
                                value={draft}
                                onChange={(e) => setDraft(e.target.value)}
                                placeholder={`Comenta como ${user.name}…`}
                            />
                            {draft.length > 150 && <span className="wv-chat__counter" aria-live="polite">{180 - draft.length}</span>}
                            <button type="submit" disabled={busy} aria-label="Enviar mensaje">
                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 12h15M12 5l7 7-7 7" /></svg>
                            </button>
                        </form>
                    ) : (
                        <div className="wv-chat__login">
                            <span>Inicia sesión para comentar.</span>
                            <a href={login}>Iniciar sesión</a>
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}

/** Color estable por persona (avatar y nombre), como en las plataformas de streaming. */
function userHue(name = "") {
    let hash = 0;
    for (const char of name) hash = (hash * 31 + char.codePointAt(0)) % 360;
    return hash;
}

function clockTime(iso) {
    const date = new Date(iso);
    if (Number.isNaN(date.getTime())) return "";
    const time = date.toLocaleTimeString("es-EC", { hour: "2-digit", minute: "2-digit" });
    return date.toDateString() === new Date().toDateString() ? time : `${date.toLocaleDateString("es-EC", { day: "numeric", month: "short" })} · ${time}`;
}
