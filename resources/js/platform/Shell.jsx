import React, { useEffect, useState } from "react";
import NavIcon from "./NavIcon";
import { Context, Action } from "./shared";
export default function Shell({ shell, children }) {
    const [menu, setMenu] = useState(false);
    useEffect(() => {
        const fn = (e) => {
            if (e.key === "Escape") setMenu(false);
        };
        window.addEventListener("keydown", fn);
        return () => window.removeEventListener("keydown", fn);
    }, []);
    return (
        <Context.Provider value={shell}>
            <div className="min-h-screen flex flex-col">
                <a className="studio-skip" href="#contenido">
                    Saltar al contenido
                </a>
                <header className="studio-header">
                    <div className="studio-header-inner">
                        <a className="studio-brand" href={shell.home}>
                            <img
                                className="brand-mascot"
                                src={shell.mascot}
                                alt="Mascota de la UEB"
                                width="48"
                                height="64"
                            />
                            <span>
                                UEB<span className="studio-brand-tv">TV</span>
                                <small>UNIVERSIDAD ESTATAL DE BOLÍVAR</small>
                            </span>
                        </a>
                        <div className="studio-account">
                            {shell.user ? (
                                <>
                                    <a
                                        className="studio-avatar"
                                        href={shell.profile}
                                        aria-label="Editar mi perfil"
                                    >
                                        {shell.user.name[0].toUpperCase()}
                                    </a>
                                    <Action
                                        url={shell.logout}
                                        className="studio-signout"
                                    >
                                        Cerrar sesión
                                    </Action>
                                </>
                            ) : (
                                <a
                                    className="studio-signout"
                                    href={shell.login}
                                >
                                    Ingresar
                                </a>
                            )}
                            {(shell.operator || !shell.user) && (
                                <button
                                    className="studio-menu-toggle"
                                    onClick={() => setMenu(!menu)}
                                    aria-expanded={menu}
                                    aria-controls="studio-sidebar"
                                    aria-label="Abrir o cerrar menú"
                                >
                                    ☰
                                </button>
                            )}
                        </div>
                    </div>
                </header>
                {shell.operator ? (
                    <aside
                        id="studio-sidebar"
                        className={`studio-sidebar ${menu ? "is-open" : ""}`}
                        aria-label="Panel de gestión"
                    >
                        <p className="studio-sidebar-caption">
                            CENTRO DE PRODUCCIÓN
                        </p>
                        <a
                            className="studio-sidebar-create"
                            href={shell.createUrl}
                            aria-current={shell.creating ? "page" : undefined}
                        >
                            <span aria-hidden="true">＋</span>Crear evento
                        </a>
                        <nav aria-label="Navegación principal">
                            {shell.links.map((l, i) => (
                                <a
                                    key={l.url}
                                    href={l.url}
                                    aria-current={l.active ? "page" : undefined}
                                >
                                    <span
                                        className="studio-sidebar-icon"
                                        aria-hidden="true"
                                    >
                                        <NavIcon index={i} />
                                    </span>
                                    {l.label}
                                </a>
                            ))}
                        </nav>
                        <div className="studio-sidebar-footer">
                            <strong>UEBTV</strong>
                            <p>Tu universidad. Tu señal.</p>
                        </div>
                    </aside>
                ) : (
                    !shell.user &&
                    menu && (
                        <nav id="studio-sidebar" className="studio-mobile-nav">
                            <a href={shell.register}>Crear cuenta ↗</a>
                        </nav>
                    )
                )}
                <div
                    className={
                        shell.operator
                            ? "studio-workspace"
                            : "flex flex-col flex-grow"
                    }
                >
                    <main id="contenido" className="flex-grow" tabIndex="-1">
                        {children}
                    </main>
                    <footer className="uebtv-footer">
                        © {shell.year} UEBTV · Universidad Estatal de Bolívar
                    </footer>
                </div>
            </div>
        </Context.Provider>
    );
}
