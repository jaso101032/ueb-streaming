import React, { useEffect, useLayoutEffect, useRef, useState } from "react";
import { useShell } from "./shared";
export default function Chat({
    endpoint,
    initial,
    canModerate,
    panelRef,
    isRecording,
    onDraft,
}) {
    const { user, login, csrf } = useShell(),
        [messages, setMessages] = useState(initial),
        [draft, setDraft] = useState(""),
        [error, setError] = useState(""),
        [connection, setConnection] = useState(""),
        [busy, setBusy] = useState(false),
        [removing, setRemoving] = useState(null),
        [version, setVersion] = useState(0);
    const list = useRef(),
        bottom = useRef(true),
        revision = useRef(0),
        controllers = useRef(new Set());
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
    }, [endpoint, version]);
    useLayoutEffect(() => {
        if (bottom.current && list.current)
            list.current.scrollTop = list.current.scrollHeight;
    }, [messages]);
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
    return (
        <aside className="uebtv-live-chat" ref={panelRef}>
            <div className="uebtv-live-chat__header">
                <div>
                    <p className="uebtv-kicker">
                        <span />
                        COMUNIDAD
                    </p>
                    <h2>{isRecording ? "Comentarios" : "Chat en vivo"}</h2>
                    <p className="watch-chat-subtitle">
                        Aquí también somos comunidad.
                    </p>
                </div>
            </div>
            <div
                ref={list}
                className="uebtv-live-chat__messages"
                aria-label="Mensajes de la comunidad"
                onScroll={() => {
                    const l = list.current;
                    bottom.current =
                        l.scrollHeight - l.scrollTop - l.clientHeight < 60;
                }}
            >
                {messages.map((m) => (
                    <div className="uebtv-chat-message" key={m.id}>
                        <span className="uebtv-chat-avatar">
                            {Array.from(m.user)[0]?.toUpperCase()}
                        </span>
                        <p>
                            <strong>{m.user}</strong>
                            {m.message}
                        </p>
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
                ))}
                {!messages.length && (
                    <div className="uebtv-chat-empty">
                        Sé el primero en compartir una idea.
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
            {user ? (
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
                            ↗
                        </button>
                    </form>
                    <p className="uebtv-live-chat__note">
                        Participas como {user.name} · Respeta a la comunidad.
                    </p>
                </>
            ) : (
                <div className="uebtv-live-chat__login">
                    <p>Inicia sesión para participar.</p>
                    <a href={login}>Iniciar sesión ↗</a>
                </div>
            )}
        </aside>
    );
}
