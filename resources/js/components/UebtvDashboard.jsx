import React from 'react';

function ArrowIcon() {
    return <span aria-hidden="true">↗</span>;
}

export default function UebtvDashboard({
    name,
    role,
    isAdmin,
    canControl,
    catalogUrl,
    adminUrl,
    streamingUrl,
    logoutUrl,
}) {
    return (
        <main className="uebtv-dashboard">
            <section className="uebtv-hero">
                <div className="uebtv-hero__content">
                    <p className="uebtv-kicker"><span /> Señal institucional UEB</p>
                    <h1>La Universidad<br /><em>en vivo.</em></h1>
                    <p className="uebtv-hero__copy">
                        Bienvenido, {name}. Encuentra eventos, conferencias y momentos que conectan a toda nuestra comunidad.
                    </p>
                    <div className="uebtv-actions">
                        <a className="uebtv-button uebtv-button--light" href={catalogUrl}>
                            Explorar transmisiones <ArrowIcon />
                        </a>
                        {canControl && (
                            <a className="uebtv-button uebtv-button--ghost" href={streamingUrl}>
                                Abrir control
                            </a>
                        )}
                    </div>
                </div>
                <div className="uebtv-hero__signal" aria-hidden="true">
                    <div className="uebtv-signal-ring uebtv-signal-ring--one" />
                    <div className="uebtv-signal-ring uebtv-signal-ring--two" />
                    <div className="uebtv-signal-core"><span>UEB</span><small>TV</small></div>
                    <div className="uebtv-live-tag"><i /> Señal activa</div>
                </div>
            </section>

            <section className="uebtv-content-grid">
                <div>
                    <div className="uebtv-section-heading">
                        <div><p className="uebtv-kicker uebtv-kicker--navy"><span /> Ahora en UEBTV</p><h2>Tu agenda audiovisual</h2></div>
                        <a href={catalogUrl}>Ver catálogo <ArrowIcon /></a>
                    </div>
                    <a className="uebtv-feature" href={catalogUrl}>
                        <div className="uebtv-feature__visual"><span className="uebtv-feature__play">▶</span><span className="uebtv-feature__duration">EN DIRECTO</span></div>
                        <div className="uebtv-feature__details"><p className="uebtv-kicker uebtv-kicker--navy"><span /> Recomendado</p><h3>Historias que hacen comunidad</h3><p>Accede a la señal oficial y descubre la programación institucional de la Universidad Estatal de Bolívar.</p><span className="uebtv-text-link">Entrar a la sala <ArrowIcon /></span></div>
                    </a>
                </div>

                <aside className="uebtv-side-panel">
                    <p className="uebtv-kicker uebtv-kicker--navy"><span /> Tu sesión</p>
                    <div className="uebtv-profile-mark">{name?.slice(0, 1).toUpperCase()}</div>
                    <h3>{name}</h3>
                    <p className="uebtv-role">{role}</p>
                    <div className="uebtv-side-links">
                        {isAdmin && <a href={adminUrl}>Panel administrador <ArrowIcon /></a>}
                        {canControl && <a href={streamingUrl}>Gestión de streaming <ArrowIcon /></a>}
                        <button type="submit" form="uebtv-logout" className="uebtv-side-link-button">Cerrar sesión <span aria-hidden="true">→</span></button>
                    </div>
                    <form id="uebtv-logout" method="POST" action={logoutUrl} className="uebtv-hidden-form">
                        <input type="hidden" name="_token" value={document.querySelector('meta[name="csrf-token"]')?.content} />
                    </form>
                </aside>
            </section>
        </main>
    );
}
