import React, { useEffect, useState } from "react";
import NavIcon from "./NavIcon";
import { Context, Action } from "./shared";
import UniversityMark from "./UniversityMark";
import UebtvMark from "./UebtvMark";

function ThemeIcon({ theme }) {
    return theme === "dark" ? (
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 15.2A8.5 8.5 0 0 1 8.8 4 8.5 8.5 0 1 0 20 15.2Z" /></svg>
    ) : (
        <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="4" /><path d="M12 2v2m0 16v2M4.9 4.9l1.4 1.4m11.4 11.4 1.4 1.4M2 12h2m16 0h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4" /></svg>
    );
}

export default function Shell({ shell, children }) {
    const [menu, setMenu] = useState(false);
    const [theme, setTheme] = useState(() => {
        if (typeof window === "undefined") return "light";
        const saved = window.localStorage.getItem("uebtv-theme");
        return saved === "dark" || saved === "light"
            ? saved
            : window.matchMedia("(prefers-color-scheme: dark)").matches
              ? "dark"
              : "light";
    });
    useEffect(() => {
        const fn = (e) => {
            if (e.key === "Escape") setMenu(false);
        };
        window.addEventListener("keydown", fn);
        return () => window.removeEventListener("keydown", fn);
    }, []);
    useEffect(() => {
        document.documentElement.dataset.theme = theme;
        window.localStorage.setItem("uebtv-theme", theme);
    }, [theme]);
    return (
        <Context.Provider value={shell}>
            <div className="min-h-screen flex flex-col">
                <a className="studio-skip" href="#contenido">
                    Saltar al contenido
                </a>
                <header className="studio-header">
                    <div className="studio-header-inner">
                        <a className="studio-brand" href={shell.home} aria-label="UEBTV">
                            <UebtvMark className="uebtv-brand-logo--header" />
                        </a>
                        <div className="studio-account">
                            <button
                                type="button"
                                className="studio-theme-toggle"
                                onClick={() => setTheme((value) => value === "dark" ? "light" : "dark")}
                                aria-label={theme === "dark" ? "Activar modo claro" : "Activar modo oscuro"}
                                title={theme === "dark" ? "Modo claro" : "Modo oscuro"}
                            >
                                <ThemeIcon theme={theme} />
                            </button>
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
                            <UniversityMark className="university-mark--sidebar" />
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
