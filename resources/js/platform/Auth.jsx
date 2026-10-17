import React, { useState, useEffect } from "react";
import { Token, Feedback, Action, Field, useShell } from "./shared";
export function Password({
    name = "password",
    label = "Contraseña",
    autoComplete = "current-password",
    minLength,
    id = name,
}) {
    const [visible, setVisible] = useState(false);
    return (
        <Field name={id} label={label}>
            <div className="react-password">
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
                    aria-label={`${visible ? "Ocultar" : "Mostrar"} ${label.toLowerCase()}`}
                    aria-pressed={visible}
                    onClick={() => setVisible(!visible)}
                >
                    {visible ? "Ocultar" : "Mostrar"}
                </button>
            </div>
        </Field>
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
        forgot = mode === "forgot",
        useUebLogo = mode === "login" || register;
    const titles = {
        login: "Bienvenido de nuevo.",
        register: "Tu comunidad empieza aquí.",
        forgot: "Recupera tu acceso.",
        reset: "Una nueva contraseña.",
        confirm: "Confirma tu identidad.",
        verify: "Revisa tu correo.",
    };
    return (
        <div className="react-auth">
            <aside className="react-auth-brand">
                <a href={shell.catalog} className="react-auth-logo" aria-label={useUebLogo ? "Universidad Estatal de Bolívar" : "FACAGEI UEBTV"}>
                    <img
                        className="react-auth-mascot"
                        src={shell.mascot}
                        alt="Mascota de la Universidad Estatal de Bolívar"
                        width="48"
                        height="72"
                    />
                    <img
                        className={useUebLogo ? "ueb-brand-logo--auth" : "facagei-brand-logo--auth"}
                        src={useUebLogo ? shell.logo : shell.headerLogo}
                        alt={useUebLogo ? "Universidad Estatal de Bolívar" : "FACAGEI · Facultad de Ciencias Administrativas, Gestión Empresarial e Informática"}
                        width={useUebLogo ? "200" : "230"}
                        height={useUebLogo ? "42" : "104"}
                    />
                </a>
                <div>
                    <span className="react-overline">
                        TU UNIVERSIDAD. TU SEÑAL.
                    </span>
                    <h2>
                        Historias que nos
                        <br />
                        <em>conectan.</em>
                    </h2>
                    <p>
                        Conocimiento, cultura y experiencias compartidas. Un
                        espacio para toda nuestra comunidad.
                    </p>
                </div>
                <span>UNIVERSIDAD ESTATAL DE BOLÍVAR</span>
            </aside>
            <section className="react-auth-content">
                <a href={shell.catalog} className="studio-text-link">
                    ← Volver al catálogo
                </a>
                <div className="react-auth-card">
                    <span className="react-auth-symbol" aria-hidden="true">
                        ↗
                    </span>
                    <p className="studio-eyebrow">UN ESPACIO PARA CONECTAR</p>
                    <h1>{titles[mode]}</h1>
                    <p>
                        {register
                            ? "Crea tu cuenta para participar y vivir cada encuentro."
                            : forgot
                              ? "Te enviaremos un enlace para restablecer tu contraseña."
                              : verify
                                ? "Abre el enlace que enviamos a tu correo para verificar tu cuenta."
                                : "Entra y sé parte de nuestra comunidad."}
                    </p>
                    <Feedback />
                    {verify ? (
                        <>
                            <Action url={action}>
                                Reenviar correo de verificación
                            </Action>
                            <Action
                                url={shell.logout}
                                className="studio-text-link"
                            >
                                Cerrar sesión
                            </Action>
                        </>
                    ) : (
                        <form
                            action={action}
                            method="POST"
                            className="react-form"
                            onSubmit={() => setBusy(true)}
                        >
                            <Token />
                            {reset && (
                                <input
                                    type="hidden"
                                    name="token"
                                    value={token}
                                />
                            )}{" "}
                            {register && (
                                <Field
                                    name="name"
                                    label="Nombre completo"
                                    defaultValue={name}
                                    required
                                    autoComplete="name"
                                    maxLength="255"
                                />
                            )}
                            {mode !== "confirm" && (
                                <Field
                                    name="email"
                                    label="Correo electrónico"
                                    type="email"
                                    defaultValue={email}
                                    required
                                    autoComplete="email"
                                />
                            )}
                            {!forgot && (
                                <Password
                                    autoComplete={
                                        register || reset
                                            ? "new-password"
                                            : "current-password"
                                    }
                                    minLength={
                                        register || reset ? 8 : undefined
                                    }
                                />
                            )}{" "}
                            {(register || reset) && (
                                <>
                                    <small>
                                        Utiliza al menos 8 caracteres,
                                        mayúsculas, minúsculas, números y
                                        símbolos.
                                    </small>
                                    <Password
                                        name="password_confirmation"
                                        label="Confirmar contraseña"
                                        autoComplete="new-password"
                                    />
                                </>
                            )}
                            {mode === "login" && (
                                <div className="react-login-options">
                                    <label>
                                        <input
                                            type="checkbox"
                                            name="remember"
                                        />{" "}
                                        Mantener mi sesión
                                    </label>
                                    <a href={forgotUrl}>¿La olvidaste?</a>
                                </div>
                            )}
                            <button className="studio-button" disabled={busy}>
                                {busy
                                    ? "Procesando…"
                                    : {
                                          login: "Entrar a UEBTV",
                                          register: "Crear mi cuenta",
                                          forgot: "Enviar enlace",
                                          reset: "Guardar contraseña",
                                          confirm: "Confirmar",
                                      }[mode]}{" "}
                                <span>↗</span>
                            </button>
                        </form>
                    )}
                    {(register || mode === "login") && (
                        <p className="react-auth-bottom">
                            {register
                                ? "¿Ya tienes cuenta?"
                                : "¿Primera vez aquí?"}{" "}
                            <a href={register ? shell.login : shell.register}>
                                {register ? "Iniciar sesión" : "Crear cuenta"}
                            </a>
                        </p>
                    )}
                </div>
            </section>
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
