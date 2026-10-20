import React, { useEffect, useRef, useState } from "react";
import NavIcon from "./NavIcon";
import { Context, Action } from "./shared";
import UniversityMark from "./UniversityMark";

function ThemeIcon({ theme }) {
    return theme === "dark" ? (
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 15.2A8.5 8.5 0 0 1 8.8 4 8.5 8.5 0 1 0 20 15.2Z" /></svg>
    ) : (
        <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="4" /><path d="M12 2v2m0 16v2M4.9 4.9l1.4 1.4m11.4 11.4 1.4 1.4M2 12h2m16 0h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4" /></svg>
    );
}

function formatStorage(bytes, unitIndex) {
    const units = ["B", "KiB", "MiB", "GiB", "TiB", "PiB"];
    const value = bytes / (1024 ** unitIndex);
    return `${new Intl.NumberFormat("es-EC", { minimumFractionDigits: 0, maximumFractionDigits: unitIndex === 0 ? 0 : 2 }).format(value)} ${units[unitIndex]}`;
}

function StorageMeter({ storage }) {
    if (!storage?.available) {
        return (
            <section className="studio-storage studio-storage--unavailable" aria-label="Almacenamiento de grabaciones">
                <div className="studio-storage__heading"><span className="studio-storage__icon" aria-hidden="true">▤</span><span>Almacenamiento</span></div>
                <p>No se pudo detectar el espacio del volumen de grabaciones.</p>
            </section>
        );
    }

    const usedPercent = Math.min(100, Math.max(0, storage.usedPercent));
    const freePercent = 100 - usedPercent;
    const lowSpace = storage.lowSpace ?? freePercent <= 10;
    const unitIndex = Math.min(5, Math.max(0, Math.floor(Math.log(Math.max(storage.totalBytes, 1)) / Math.log(1024))));

    return (
        <section className={`studio-storage ${lowSpace ? "studio-storage--low" : ""}`} aria-label="Almacenamiento de medios">
            <div className="studio-storage__heading">
                <span className="studio-storage__icon" aria-hidden="true">▤</span>
                <span>{storage.basis === "quota" ? "Cuota de medios UEBTV" : "Volumen de grabaciones"}</span>
            </div>
            <div className="studio-storage__free">{formatStorage(storage.freeBytes, unitIndex)} <span>libres</span></div>
            <div className="studio-storage__track" role="progressbar" aria-label="Espacio usado" aria-valuemin="0" aria-valuemax="100" aria-valuenow={usedPercent}>
                <span style={{ width: `${usedPercent}%` }} />
            </div>
            <div className="studio-storage__details">
                <span>{formatStorage(storage.usedBytes, unitIndex)} usados</span>
                <span>de {formatStorage(storage.totalBytes, unitIndex)}</span>
            </div>
            <p className="studio-storage__note">Medios UEBTV: {formatStorage(storage.mediaUsedBytes ?? 0, unitIndex)}{storage.basis === "filesystem" ? " · total físico del volumen" : ""}</p>
            {storage.physicalLimited && <p className="studio-storage__note">El espacio físico disponible es menor que el cupo restante.</p>}
            {lowSpace && <p className="studio-storage__warning" role="alert">Espacio bajo: revisa el almacenamiento antes de subir o grabar más contenido.</p>}
        </section>
    );
}

export default function Shell({ shell, children }) {
    const [menu, setMenu] = useState(false);
    const menuRef = useRef(null), menuButtonRef = useRef(null);
    const currentPath = typeof window === "undefined" ? "" : window.location.pathname;
    const channelPath = new URL(shell.channel, "http://localhost").pathname;
    const catalogPath = new URL(shell.catalog, "http://localhost").pathname;
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
    useEffect(() => {
        if (!menu || shell.operator) return undefined;
        const closeOutside = (event) => {
            if (!menuRef.current?.contains(event.target) && !menuButtonRef.current?.contains(event.target)) setMenu(false);
        };
        document.addEventListener("pointerdown", closeOutside);
        return () => document.removeEventListener("pointerdown", closeOutside);
    }, [menu, shell.operator]);
    return (
        <Context.Provider value={shell}>
            <div className="min-h-screen flex flex-col">
                <a className="studio-skip" href="#contenido">
                    Saltar al contenido
                </a>
                <header className={`studio-header ${shell.operator ? "studio-header--operator" : ""}`}>
                    <div className="studio-header-inner">
                        <a className="studio-brand studio-brand--facagei" href={shell.home} aria-label="FACAGEI UEBTV">
                            <img
                                className="studio-brand-mascot"
                                src={shell.mascot}
                                alt=""
                                aria-hidden="true"
                                width="58"
                                height="84"
                            />
                            <img
                                className="facagei-brand-logo--header"
                                src={shell.headerLogo}
                                alt="FACAGEI · Facultad de Ciencias Administrativas, Gestión Empresarial e Informática"
                                width="1665"
                                height="945"
                            />
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
                                        ariaLabel="Cerrar sesión"
                                    >
                                        <span className="studio-signout-full">Cerrar sesión</span>
                                        <span className="studio-signout-short" aria-hidden="true">Salir</span>
                                    </Action>
                                </>
                            ) : (
                                <a
                                    className="studio-signout"
                                    href={shell.login}
                                >
                                    Iniciar sesión
                                </a>
                            )}
                            <button
                                ref={menuButtonRef}
                                type="button"
                                className={`studio-menu-toggle ${menu ? "is-open" : ""}`}
                                onClick={() => setMenu((value) => !value)}
                                aria-expanded={menu}
                                aria-controls={shell.operator ? "studio-sidebar" : "studio-guest-menu"}
                                aria-label={menu ? "Cerrar menú" : "Abrir menú"}
                            >
                                <svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round">
                                    {menu ? <path d="M5 5l14 14M19 5 5 19" /> : <path d="M4 7h16M4 12h16M4 17h16" />}
                                </svg>
                            </button>
                        </div>
                    </div>
                    {!shell.operator && menu && (
                        <nav ref={menuRef} id="studio-guest-menu" className="studio-guest-menu" aria-label="Menú principal">
                            <p className="studio-guest-menu__eyebrow">EXPLORA UEBTV</p>
                            <a href={shell.channel} aria-current={currentPath === channelPath ? "page" : undefined}>
                                <span className="studio-guest-menu__icon" aria-hidden="true"><NavIcon index={2} /></span>
                                <span className="studio-guest-menu__copy"><strong>Señal en vivo</strong><small>Directos y programación 24/7</small></span>
                                <span className="studio-guest-menu__arrow" aria-hidden="true">↗</span>
                            </a>
                            <a href={shell.catalog} aria-current={currentPath.startsWith(catalogPath) ? "page" : undefined}>
                                <span className="studio-guest-menu__icon" aria-hidden="true"><NavIcon index={3} /></span>
                                <span className="studio-guest-menu__copy"><strong>Explorar catálogo</strong><small>Eventos y videos de la UEB</small></span>
                                <span className="studio-guest-menu__arrow" aria-hidden="true">↗</span>
                            </a>
                            <div className="studio-guest-menu__account">
                                <span>{shell.user ? "TU CUENTA" : "ÚNETE A LA COMUNIDAD"}</span>
                                <a href={shell.user ? shell.profile : shell.register}>
                                    {shell.user ? "Mi perfil" : "Crear cuenta"}<span aria-hidden="true">↗</span>
                                </a>
                            </div>
                        </nav>
                    )}
                </header>
                {shell.operator ? (
                    <aside
                        id="studio-sidebar"
                        className={`studio-sidebar ${menu ? "is-open" : ""}`}
                        aria-label="Panel de gestión"
                    >
                        <p className="studio-sidebar-caption">
                            {shell.panelLabel || "CENTRO DE PRODUCCIÓN"}
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
                                        <NavIcon index={l.icon ?? i} />
                                    </span>
                                    {l.label}
                                </a>
                            ))}
                        </nav>
                        {shell.user?.role === "admin" && <StorageMeter storage={shell.storage} />}
                        <div className="studio-sidebar-footer">
                            <UniversityMark className="university-mark--sidebar" />
                        </div>
                    </aside>
                ) : null}
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
                        Desarrolladores · Javier Solis · Cristhian Rochina · Universidad Estatal de Bolívar
                    </footer>
                </div>
            </div>
        </Context.Provider>
    );
}
