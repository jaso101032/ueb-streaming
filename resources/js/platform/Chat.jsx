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
        [mobileOpen, setMobileOpen] = useState(false),
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
    useLayoutEffect(() => {
        if (bottom.current && list.current)
            list.current.scrollTop = list.current.scrollHeight;
    }, [messages, playbackSeconds]);
    const reachedMessages = replay
        ? messages.filter((m) => m.playback_seconds == null || m.playback_seconds <= playbackSeconds)
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
    const latestMessage = visibleMessages.at(-1);
    return (
        <aside className={`uebtv-live-chat ${mobileOpen ? "is-mobile-open" : "is-mobile-collapsed"}`} ref={panelRef}>
            <div className="uebtv-live-chat__header">
                <div>
                    <p className="uebtv-kicker">
                        <span />
                        COMUNIDAD
                    </p>
                    <h2>{isRecording ? "Comentarios" : "Chat en vivo"}</h2>
                    <p className="watch-chat-subtitle">
                        {commentsOpen ? "Comparte el momento con la comunidad." : !canComment ? "Mensajes conservados junto a cada momento del video." : restricted ? "Tu cuenta no puede comentar; puedes leer la conversación." : "Los comentarios están desactivados; el historial sigue visible."}
                    </p>
                </div>
                <div className="uebtv-live-chat__header-actions">
                    <span className={`uebtv-chat-status ${commentsOpen ? "is-open" : "is-closed"}`}>
                        <i aria-hidden="true" />{commentsOpen ? "Activo" : "Solo lectura"}
                    </span>
                    <button type="button" className="uebtv-chat-expand" aria-controls="uebtv-chat-content" aria-expanded={mobileOpen} aria-label={mobileOpen ? "Ocultar comentarios" : "Mostrar comentarios"} onClick={() => setMobileOpen((open) => !open)}>
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 9 6 6 6-6" /></svg>
                    </button>
                </div>
            </div>
            <p className="uebtv-chat-preview">
                {latestMessage ? <><strong>{latestMessage.user}</strong><span>{latestMessage.message}</span></> : replay ? "Los comentarios aparecerán mientras avanza el video." : "Abre el chat para participar en la conversación."}
            </p>
            <div className="uebtv-live-chat__content" id="uebtv-chat-content">
            {chatToggleUrl && canComment && <form method="POST" action={chatToggleUrl} className="uebtv-chat-control">
                <input type="hidden" name="_token" value={csrf} />
                <input type="hidden" name="enabled" value={enabled ? "0" : "1"} />
                <button type="submit">{enabled ? "Desactivar comentarios" : "Activar comentarios"}</button>
            </form>}
            <div
                ref={list}
                className="uebtv-live-chat__messages"
                role="log"
                aria-label="Mensajes de la comunidad"
                onScroll={() => {
                    const l = list.current;
                    bottom.current =
                        l.scrollHeight - l.scrollTop - l.clientHeight < 60;
                }}
            >
                {earlierCount > 0 && <button type="button" className="uebtv-chat-history" onClick={() => {
                    bottom.current = false;
                    setVisibleLimit((limit) => limit + 80);
                }}>Ver {Math.min(earlierCount, 80)} comentarios anteriores</button>}
                {visibleMessages.map((m) => (
                    <div className="uebtv-chat-message" key={m.id}>
                        <span className="uebtv-chat-avatar">
                            {Array.from(m.user)[0]?.toUpperCase()}
                        </span>
                        <div className="uebtv-chat-message__body">
                            <div className="uebtv-chat-message__meta">
                                <strong>{m.user}</strong>
                                {canModerate && (
                                    <button
                                        type="button"
                                        className="uebtv-chat-remove"
                                        aria-label={`Retirar mensaje de ${m.user}`}
                                        disabled={removing === m.id}
                                        onClick={() => remove(m)}
                                    >
                                        Retirar
                                    </button>
                                )}
                            </div>
                            <p className="uebtv-chat-bubble">{m.message}</p>
                            {isRecording && m.playback_seconds != null && (
                                seekable && onSeek ? (
                                    <button
                                        type="button"
                                        className="uebtv-chat-time"
                                        onClick={() => onSeek(m.playback_seconds)}
                                        aria-label={`Ir al minuto ${formatPlaybackTime(m.playback_seconds)} de la grabación`}
                                        title="Ver este momento de la grabación"
                                    >
                                        <span aria-hidden="true">▶</span>Ver momento {formatPlaybackTime(m.playback_seconds)}
                                    </button>
                                ) : (
                                    <span className="uebtv-chat-time" title="Minuto de la emisión">
                                        Momento {formatPlaybackTime(m.playback_seconds)}
                                    </span>
                                )
                            )}
                        </div>
                    </div>
                ))}
                {!visibleMessages.length && !connection && (
                    <div className="uebtv-chat-empty">
                        {replay ? "Los comentarios aparecerán al llegar a su momento en el video." : "Sé el primero en compartir una idea."}
                    </div>
                )}
            </div>
            <p className="uebtv-chat-feedback" role="status">
                {connection}
            </p>
            {error && (
                <p
                    id="chatError"
                    className="uebtv-chat-feedback is-error"
                    role="alert"
                >
                    {error}
                </p>
            )}
            {canModerate && (
                <p className="uebtv-live-chat__note">
                    Moderación: puedes retirar mensajes inapropiados.
                </p>
            )}
            {!commentsOpen ? (
                <div className="uebtv-live-chat__closed" role="status">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="10" width="14" height="10" rx="2" /><path d="M8 10V7a4 4 0 0 1 8 0v3" /></svg>
                    <p><strong>{!canComment ? "El chat terminó con la transmisión." : restricted ? "Tu cuenta tiene restringidos los comentarios." : "Los comentarios están desactivados."}</strong> Puedes seguir leyendo los mensajes publicados.</p>
                </div>
            ) : user ? (
                <>
                    <form className="uebtv-live-chat__form" onSubmit={send}>
                        <input
                            aria-label="Tu mensaje"
                            aria-describedby={error ? "chatError" : undefined}
                            required
                            maxLength={180}
                            value={draft}
                            onChange={(e) => {
                                setDraft(e.target.value);
                            }}
                            placeholder="Comparte una idea…"
                        />
                        <button
                            type="submit"
                            disabled={busy}
                            aria-label="Enviar mensaje"
                        >
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M4 12h15M12 5l7 7-7 7" />
                            </svg>
                        </button>
                    </form>
                    <p className="uebtv-live-chat__note">
                        Participas como {user.name} · Respeta a la comunidad.
                    </p>
                </>
            ) : (
                <div className="uebtv-live-chat__login">
                    <p>Inicia sesión para participar.</p>
                    <a className="uebtv-live-chat__login-action" href={login}>
                        Iniciar sesión ↗
                    </a>
                </div>
            )}
            </div>
        </aside>
    );
}
