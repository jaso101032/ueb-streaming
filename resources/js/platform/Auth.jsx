import React, { useState, useEffect } from "react";
import { Token, Feedback, Action, Field, useShell } from "./shared";
const AuthIcon = ({ name }) => {
    const paths = {
        mail: <><rect x="3" y="5" width="18" height="14" rx="2.5" /><path d="m4 7 8 6 8-6" /></>,
        lock: <><rect x="5" y="10.5" width="14" height="10" rx="2.5" /><path d="M8 10.5V8a4 4 0 0 1 8 0v2.5" /></>,
        user: <><circle cx="12" cy="8" r="4" /><path d="M4.5 20.5a7.5 7.5 0 0 1 15 0" /></>,
        eye: <><path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12Z" /><circle cx="12" cy="12" r="3" /></>,
        eyeOff: <><path d="M3 3l18 18M10.6 5.6A9.6 9.6 0 0 1 12 5.5C18 5.5 21.5 12 21.5 12a17 17 0 0 1-3.3 4.1M6.3 6.9C3.9 8.6 2.5 12 2.5 12S6 18.5 12 18.5a9 9 0 0 0 4.2-1" /><path d="M9.9 9.9a3 3 0 0 0 4.2 4.2" /></>,
        back: <path d="M15 5l-7 7 7 7" />,
        arrow: <path d="M5 12h14m-6-6 6 6-6 6" />,
    };
    return <svg className="au-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">{paths[name]}</svg>;
};

/** Campo con ícono a la izquierda (correo, nombre…). */
function AuthField({ name, label, icon, ...props }) {
    return (
        <div className="au-field">
            <label htmlFor={name}>{label}</label>
            <div className="au-input">
                <AuthIcon name={icon} />
                <input id={name} name={name} {...props} />
            </div>
        </div>
    );
}

export function Password({
    name = "password",
    label = "Contraseña",
    autoComplete = "current-password",
    minLength,
    id = name,
    aside,
}) {
    const [visible, setVisible] = useState(false);
    return (
        <div className="au-field">
            <div className="au-field__label">
                <label htmlFor={id}>{label}</label>
                {aside}
            </div>
            <div className="au-input react-password">
                <AuthIcon name="lock" />
                <input
                    id={id}
                    name={name}
                    type={visible ? "text" : "password"}
                    autoComplete={autoComplete}
                    required
                    minLength={minLength}
                />
                <button
                    type="button"
                    className="au-input__eye"
                    aria-label={`${visible ? "Ocultar" : "Mostrar"} ${label.toLowerCase()}`}
                    aria-pressed={visible}
                    title={visible ? "Ocultar" : "Mostrar"}
                    onClick={() => setVisible(!visible)}
                >
                    <AuthIcon name={visible ? "eyeOff" : "eye"} />
                </button>
            </div>
        </div>
    );
}

export default function Auth({ mode, action, name, email, token, forgotUrl }) {
    const shell = useShell(),
        [busy, setBusy] = useState(false);
    useEffect(() => {
        const reset = () => setBusy(false);
        window.addEventListener("pageshow", reset);
        return () => window.removeEventListener("pageshow", reset);
    }, []);
    const register = mode === "register",
        reset = mode === "reset",
        verify = mode === "verify",
        forgot = mode === "forgot";
    const titles = {
        login: "Inicia sesión",
        register: "Crea tu cuenta",
        forgot: "Recupera tu acceso",
        reset: "Nueva contraseña",
        confirm: "Confirma tu identidad",
        verify: "Revisa tu correo",
    };
    const subtitles = {
        login: "Bienvenido de nuevo a UEBTV.",
        register: "Únete para comentar y vivir cada encuentro.",
        forgot: "Te enviaremos un enlace para restablecer tu contraseña.",
        reset: "Elige una contraseña segura para tu cuenta.",
        confirm: "Por seguridad, confirma tu contraseña para continuar.",
        verify: "Abre el enlace que enviamos a tu correo para verificar tu cuenta.",
    };
    const submit = {
        login: "Entrar a UEBTV",
        register: "Crear mi cuenta",
        forgot: "Enviar enlace",
        reset: "Guardar contraseña",
        confirm: "Confirmar",
    }[mode];
    return (
        <div className="au">
            {/* Lado de marca (escritorio): siempre oscuro, como la entrada a la señal. */}
            <aside className="au-brand">
                <div className="au-brand__glow" aria-hidden="true" />
                <a href={shell.catalog} className="au-brand__logo" aria-label="Universidad Estatal de Bolívar">
                    <img className="au-brand__mascot" src={shell.mascot} alt="" width="48" height="72" />
                    <img className="au-brand__ueb" src={shell.logo} alt="Universidad Estatal de Bolívar" width="200" height="42" />
                </a>
                <div className="au-brand__copy">
                    <a className="au-live" href={shell.channel}><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="6" width="18" height="12" rx="2" /><path d="M8 3l4 3 4-3" /></svg>Señal 24/7 de la UEB</a>
                    <h2>Historias que nos <em>conectan.</em></h2>
                    <p>Conferencias, cultura y momentos de nuestra comunidad, en directo y cuando quieras.</p>
                    <ul className="au-features">
                        <li><span aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="2.5" /><path d="M7.8 7.8a6 6 0 0 0 0 8.4m8.4-8.4a6 6 0 0 1 0 8.4M5 5a10 10 0 0 0 0 14m14-14a10 10 0 0 1 0 14" /></svg></span><div><strong>Señal 24/7 y directos</strong><small>Programación continua y transmisiones en vivo.</small></div></li>
                        <li><span aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="3.5" y="5" width="17" height="14" rx="2.5" /><path d="m10 9 5 3-5 3z" /></svg></span><div><strong>Grabaciones cuando quieras</strong><small>Vuelve a ver conferencias y eventos.</small></div></li>
                        <li><span aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M5 6h14v9H9l-4 3z" /></svg></span><div><strong>Comenta en vivo</strong><small>Participa con tu comunidad en cada emisión.</small></div></li>
                    </ul>
                </div>
                <small className="au-brand__foot">UEBTV · Universidad Estatal de Bolívar</small>
            </aside>
            <main className="au-main">
                <div className="au-top">
                    <div className="au-top__row">
                        <a href={shell.catalog} className="au-back"><AuthIcon name="back" />Volver al catálogo</a>
                        <a href={shell.catalog} className="au-top__logo" aria-label="UEBTV">
                            <img src={shell.mascot} alt="" width="24" height="36" />
                            <img src={shell.logo} alt="Universidad Estatal de Bolívar" width="120" height="25" />
                        </a>
                    </div>
                    {/* Solo en el celular: franja de marca sobre el formulario. */}
                    <div className="au-top__hero">
                        <a className="au-live" href={shell.channel}><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="6" width="18" height="12" rx="2" /><path d="M8 3l4 3 4-3" /></svg>Señal 24/7 de la UEB</a>
                        <p>Historias que nos <em>conectan.</em></p>
                    </div>
                </div>
                <div className="au-form-wrap">
                    <h1>{titles[mode]}</h1>
                    <p className="au-sub">{subtitles[mode]}</p>
                    <Feedback />
                    {verify ? (
                        <div className="au-actions">
                            <Action url={action} className="au-submit">Reenviar correo de verificación</Action>
                            <Action url={shell.logout} className="au-link">Cerrar sesión</Action>
                        </div>
                    ) : (
                        <form action={action} method="POST" className="au-form" onSubmit={() => setBusy(true)}>
                            <Token />
                            {reset && <input type="hidden" name="token" value={token} />}
                            {register && (
                                <AuthField name="name" label="Nombre completo" icon="user" defaultValue={name} required autoComplete="name" maxLength="255" placeholder="Tu nombre y apellido" />
                            )}
                            {mode !== "confirm" && (
                                <AuthField name="email" label="Correo electrónico" icon="mail" type="email" defaultValue={email} required autoComplete="email" placeholder="tucorreo@ueb.edu.ec" />
                            )}
                            {!forgot && (
                                <Password
                                    autoComplete={register || reset ? "new-password" : "current-password"}
                                    minLength={register || reset ? 8 : undefined}
                                    aside={mode === "login" ? <a className="au-link" href={forgotUrl}>¿Olvidaste tu contraseña?</a> : null}
                                />
                            )}
                            {(register || reset) && (
                                <>
                                    <p className="au-hint">Al menos 8 caracteres, con mayúsculas, minúsculas, números y símbolos.</p>
                                    <Password name="password_confirmation" label="Confirmar contraseña" autoComplete="new-password" />
                                </>
                            )}
                            {mode === "login" && (
                                <label className="au-check">
                                    <input type="checkbox" name="remember" />
                                    <span>Mantener mi sesión iniciada</span>
                                </label>
                            )}
                            <button className="au-submit" disabled={busy} aria-busy={busy}>
                                {busy ? <><span className="au-spinner" aria-hidden="true" />{mode === "login" ? "Entrando…" : "Procesando…"}</> : <>{submit}<AuthIcon name="arrow" /></>}
                            </button>
                        </form>
                    )}
                    {(register || mode === "login") && (
                        <p className="au-switch">
                            {register ? "¿Ya tienes cuenta?" : "¿Primera vez aquí?"}{" "}
                            <a href={register ? shell.login : shell.register}>{register ? "Inicia sesión" : "Crea tu cuenta"}</a>
                        </p>
                    )}
                    {(forgot || reset || mode === "confirm") && (
                        <p className="au-switch"><a href={shell.login}>← Volver a iniciar sesión</a></p>
                    )}
                </div>
                {/* Solo en el celular: lo que ofrece la plataforma, al pie. */}
                <ul className="au-mini" aria-label="Qué encontrarás en UEBTV">
                    <li><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="2.5" /><path d="M7.8 7.8a6 6 0 0 0 0 8.4m8.4-8.4a6 6 0 0 1 0 8.4M5 5a10 10 0 0 0 0 14m14-14a10 10 0 0 1 0 14" /></svg>Señal 24/7</li>
                    <li><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3.5" y="5" width="17" height="14" rx="2.5" /><path d="m10 9 5 3-5 3z" /></svg>Grabaciones</li>
                    <li><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 6h14v9H9l-4 3z" /></svg>Chat en vivo</li>
                </ul>
                <small className="au-foot">UEBTV · Universidad Estatal de Bolívar</small>
            </main>
        </div>
    );
}
export function Profile(p) {
    const [deleting, setDeleting] = useState(false);
    return (
        <div className="studio-page">
            <div className="studio-heading">
                <div>
                    <p className="studio-eyebrow">MI CUENTA</p>
                    <h1>Tu espacio personal.</h1>
                    <p>Actualiza tu información y protege tu acceso.</p>
                </div>
            </div>
            <Feedback />
            <div className="react-profile-grid">
                <section className="studio-panel react-panel">
                    <h2>Información del perfil</h2>
                    <form
                        className="react-form"
                        method="POST"
                        action={p.updateUrl}
                    >
                        <Token method="PATCH" />
                        <Field
                            name="name"
                            label="Nombre"
                            defaultValue={p.name}
                            autoComplete="name"
                            required
                        />
                        <Field
                            name="email"
                            label="Correo electrónico"
                            type="email"
                            defaultValue={p.email}
                            autoComplete="email"
                            required
                        />
                        <button className="studio-button">
                            Guardar perfil
                        </button>
                    </form>
                    {p.unverified && (
                        <Action url={p.verifyUrl}>Reenviar verificación</Action>
                    )}
                </section>
                <section className="studio-panel react-panel">
                    <h2>Cambiar contraseña</h2>
                    {p.passwordErrors.map((e, i) => (
                        <p role="alert" key={i}>
                            {e}
                        </p>
                    ))}
                    <form
                        className="react-form"
                        method="POST"
                        action={p.passwordUrl}
                    >
                        <Token method="PUT" />
                        <Password
                            name="current_password"
                            label="Contraseña actual"
                        />
                        <Password
                            label="Nueva contraseña"
                            autoComplete="new-password"
                            minLength={8}
                        />
                        <p className="react-password-requirements">Debe tener al menos 8 caracteres e incluir mayúsculas, minúsculas, números y símbolos (por ejemplo, ! o #).</p>
                        <Password
                            name="password_confirmation"
                            label="Confirmar nueva contraseña"
                            autoComplete="new-password"
                        />
                        <button className="studio-button">
                            Actualizar contraseña
                        </button>
                    </form>
                </section>
                <section className="studio-panel react-panel react-account-danger">
                    <h2>Eliminar cuenta</h2>
                    <p>
                        Esta acción es permanente. Confirma tu contraseña para
                        eliminar tu cuenta.
                    </p>
                    {p.deleteErrors.map((e, i) => (
                        <p role="alert" key={i}>
                            {e}
                        </p>
                    ))}
                    <button
                        className="react-danger"
                        onClick={() => setDeleting(!deleting)}
                        aria-expanded={deleting}
                    >
                        {deleting ? "Cancelar" : "Eliminar mi cuenta"}
                    </button>
                    {deleting && (
                        <form
                            className="react-form"
                            action={p.deleteUrl}
                            method="POST"
                            onSubmit={(e) => {
                                if (
                                    !window.confirm(
                                        "¿Eliminar tu cuenta permanentemente?",
                                    )
                                )
                                    e.preventDefault();
                            }}
                        >
                            <Token method="DELETE" />
                            <Password id="delete_password" />
                            <button className="react-danger">
                                Confirmar eliminación
                            </button>
                        </form>
                    )}
                </section>
            </div>
        </div>
    );
}
