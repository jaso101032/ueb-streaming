import React from "react";
import { Heading, Pagination } from "./shared";

const actionNames = {
    "evento.creado": "Evento creado",
    "evento.actualizado": "Evento actualizado",
    "evento.eliminado": "Evento eliminado",
    "transmision.clave_generada": "Clave de OBS generada",
    "transmision.iniciada": "Señal de OBS recibida",
    "transmision.finalizada": "Transmisión finalizada",
    "grabacion.aprobada": "Grabación aprobada",
    "grabacion.publicada_automaticamente": "Grabación publicada automáticamente",
    "grabacion.pendiente_revision": "Grabación pendiente de revisión",
    "subtitulos.agregados": "Subtítulos agregados",
    "subtitulos.reemplazados": "Subtítulos reemplazados",
    "subtitulos.eliminados": "Subtítulos eliminados",
    "grabacion.retirada": "Grabación retirada",
    "grabacion.descargada": "Grabación descargada",
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
    "marca_agua.actualizada": "Marca de agua actualizada",
    "marca_agua.eliminada": "Marca de agua eliminada",
};

const describe = (details) => (details ? JSON.stringify(details, null, 1).replace(/[{}"]/g, "").trim() : "");

export default function AuditLog({ logs, pagination, filters, actions }) {
    return (
        <div className="studio-page users-page audit-page">
            <Heading eyebrow="ADMINISTRACIÓN" title="Bitácora de auditoría">
                Quién realizó cada operación sensible, cuándo y sobre qué recurso. Los registros no se pueden editar desde la plataforma.
            </Heading>
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
                                        <td>{actionNames[log.action] || log.action}</td>
                                        <td>{log.subject || "—"}</td>
                                        <td><pre>{describe(log.details) || "—"}</pre></td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                ) : <div className="studio-empty"><h3>Sin registros</h3><p>Las operaciones sensibles aparecerán aquí.</p></div>}
                <div className="studio-pagination"><Pagination pagination={pagination} /></div>
            </section>
        </div>
    );
}
