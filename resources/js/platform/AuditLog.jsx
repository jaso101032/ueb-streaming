import React from "react";
import { PageHeader, Pagination } from "./shared";

const actionNames = {
    "evento.creado": "Evento creado",
    "evento.actualizado": "Evento actualizado",
    "evento.eliminado": "Evento eliminado",
    "evento.eliminado_definitivo": "Evento y archivos borrados",
    "almacenamiento.calidades_quitadas": "Calidades quitadas para liberar espacio",
    "almacenamiento.calidades_solicitadas": "Calidades preparadas de nuevo",
    "almacenamiento.original_borrado": "Original de OBS borrado",
    "transmision.clave_generada": "Clave de OBS generada",
    "transmision.iniciada": "Señal de OBS recibida",
    "transmision.finalizada": "Transmisión finalizada",
    "grabacion.aprobada": "Grabación aprobada",
    "grabacion.publicada_automaticamente": "Grabación publicada automáticamente",
    "grabacion.pendiente_revision": "Grabación pendiente de revisión",
    "subtitulos.agregados": "Subtítulos agregados",
    "subtitulos.reemplazados": "Subtítulos reemplazados",
    "subtitulos.eliminados": "Subtítulos eliminados",
    "subtitulos.generados_automaticamente": "Subtítulos generados automáticamente",
    "subtitulos.generacion_solicitada": "Generación de subtítulos solicitada",
    "grabacion.retirada": "Grabación retirada",
    "grabacion.descargada": "Grabación descargada",
    "video.descargado": "Video descargado",
    "video.subido": "Video subido al catálogo",
    "grabacion.reintento": "Reintento de grabación",
    "usuario.creado": "Usuario creado",
    "usuario.actualizado": "Usuario actualizado",
    "usuario.eliminado": "Usuario eliminado",
    "chat.habilitado": "Comentarios habilitados",
    "chat.deshabilitado": "Comentarios deshabilitados",
    "chat.mensaje_moderado": "Mensaje moderado",
    "programacion.creada": "Emisión programada",
    "publicidad.creada": "Publicidad creada",
    "publicidad.activada": "Publicidad activada",
    "publicidad.desactivada": "Publicidad desactivada",
    "publicidad.eliminada": "Publicidad eliminada",
    "marca_agua.actualizada": "Marca de agua actualizada",
    "marca_agua.eliminada": "Marca de agua eliminada",
};

/** Ícono y color según el tipo de operación (primera parte del código). */
const KINDS = {
    evento: ["Eventos", "M3.5 5h17v15h-17zM8 3v4m8-4v4M3.5 10h17", "info"],
    transmision: ["Transmisión", "M12 12h.01M7.8 7.8a6 6 0 0 0 0 8.4m8.4-8.4a6 6 0 0 1 0 8.4M5 5a10 10 0 0 0 0 14m14-14a10 10 0 0 1 0 14", "live"],
    grabacion: ["Grabación", "M3.5 4h17v16h-17zm6.5 5 5 3-5 3z", "ok"],
    subtitulos: ["Subtítulos", "M4 6h16v12H4zM8 11h3m2 0h3M8 14h5", "info"],
    video: ["Video", "M12 4v11m-5-5 5 5 5-5M5 20h14", "info"],
    usuario: ["Usuarios", "M9 11.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7zM2.5 20a6.5 6.5 0 0 1 13 0", "user"],
    chat: ["Comentarios", "M5 6h14v9H9l-4 3z", "info"],
    programacion: ["Parrilla", "M12 3.5a8.5 8.5 0 1 0 0 17 8.5 8.5 0 0 0 0-17zM12 7.5V12l3 2", "info"],
    publicidad: ["Publicidad", "M4 10v4h3l7 4V6l-7 4H4Zm13-1.5a4 4 0 0 1 0 7", "warn"],
    almacenamiento: ["Almacenamiento", "M4 6c0-1.7 3.6-3 8-3s8 1.3 8 3v12c0 1.7-3.6 3-8 3s-8-1.3-8-3zM4 6c0 1.7 3.6 3 8 3s8-1.3 8-3M4 12c0 1.7 3.6 3 8 3s8-1.3 8-3", "warn"],
    marca_agua: ["Marca de agua", "M3.5 4.5h17v15h-17zM9 11.8a1.8 1.8 0 1 0 0-3.6 1.8 1.8 0 0 0 0 3.6zM20.5 16l-5-5L6 19.5", "info"],
};
const kindOf = (action) => {
    const [group] = String(action).split(".");
    const tone = /eliminad|retirad|deshabilitad|desactivad|moderado|borrad|quitadas/.test(action) ? "bad" : (KINDS[group]?.[2] || "info");
    return { label: KINDS[group]?.[0] || group, path: KINDS[group]?.[1] || "M12 3.5a8.5 8.5 0 1 0 0 17 8.5 8.5 0 0 0 0-17z", tone };
};

const describe = (details) => (details ? JSON.stringify(details, null, 1).replace(/[{}"]/g, "").trim() : "");

export default function AuditLog({ logs, pagination, filters, actions }) {
    return (
        <div className="studio-page users-page audit-page pg">
            <PageHeader eyebrow="Administración" title="Auditoría" description="Quién hizo cada operación importante, cuándo y sobre qué. Los registros no se pueden editar." />
            <section className="studio-panel studio-management users-list">
                <div className="studio-panel-heading"><h2>Registros</h2><span>{pagination.total} operaciones</span></div>
                <form method="GET" className="users-filters">
                    <label>Buscar<input name="q" defaultValue={filters.q} placeholder="Persona o recurso" /></label>
                    <label>Operación<select name="accion" defaultValue={filters.accion}><option value="">Todas</option>{actions.map((a) => <option key={a} value={a}>{actionNames[a] || a}</option>)}</select></label>
                    <button className="studio-button studio-button-soft">Filtrar</button>
                </form>
                {logs.length ? (
                    <div className="audit-table-wrap">
                        <table className="audit-table">
                            <thead><tr><th scope="col">Fecha y hora</th><th scope="col">Responsable</th><th scope="col">Operación</th><th scope="col">Recurso</th><th scope="col">Detalle</th></tr></thead>
                            <tbody>
                                {logs.map((log) => (
                                    <tr key={log.id}>
                                        <td>{log.when}</td>
                                        <td>{log.who}{log.ip && <small>{log.ip}</small>}</td>
                                        <td>{(() => { const k = kindOf(log.action); return <span className={`audit-op is-${k.tone}`}><span className="audit-op__icon" title={k.label}><svg viewBox="0 0 24 24" aria-hidden="true"><path d={k.path} /></svg></span>{actionNames[log.action] || log.action}</span>; })()}</td>
                                        <td>{log.subject || "—"}</td>
                                        <td><pre>{describe(log.details) || "—"}</pre></td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                ) : <div className="studio-empty audit-empty">
                    <span className="audit-empty__icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M12 3l7.5 3v5.5c0 4.5-3.2 8.2-7.5 9.5-4.3-1.3-7.5-5-7.5-9.5V6z M8.5 12l2.5 2.5 4.5-5" /></svg></span>
                    <h3>{filters.q || filters.accion ? "No hay registros con estos filtros" : "Aún no hay registros"}</h3>
                    <p>Aquí quedará constancia de cada operación importante: crear o eliminar eventos, generar claves de OBS, publicar o retirar grabaciones, cambios de usuarios, publicidad y marca de agua.</p>
                </div>}
                <div className="studio-pagination"><Pagination pagination={pagination} /></div>
            </section>
        </div>
    );
}
