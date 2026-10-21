import React from "react";
import { Action, Feedback, Field, Heading, Pagination, Token } from "./shared";

const roleNames = { admin: "Administrador", operador: "Operador", usuario: "Usuario" };
const permissionNames = {
    events: "Crear y editar eventos",
    stream: "Emitir con OBS y finalizar directos",
    moderate_chat: "Retirar mensajes del chat",
    toggle_chat: "Activar o desactivar comentarios",
};

function AccountControls({ role, permissions = [], restricted = false, actions }) {
    return <>
        {role === "operador" && <fieldset className="users-permissions">
            <legend>Permisos del operador</legend>
            <input type="hidden" name="permissions_present" value="1" />
            {actions.map((action) => <label key={action}>
                <input type="checkbox" name="permissions[]" value={action} defaultChecked={permissions.includes(action)} />
                <span>{permissionNames[action]}</span>
            </label>)}
            <small>La descarga y eliminación de grabaciones, la biblioteca y la gestión de usuarios siguen siendo exclusivas del administrador.</small>
        </fieldset>}
        <label className="users-restriction"><input type="hidden" name="chat_restricted" value="0" /><input type="checkbox" name="chat_restricted" value="1" defaultChecked={restricted} /> Restringir comentarios de esta cuenta</label>
    </>;
}

function UserEdit({ user, roles, actions }) {
    const [role, setRole] = React.useState(user.role);
    return (
        <details className="users-edit">
            <summary aria-label={`Editar cuenta de ${user.name}`} title={`Editar cuenta de ${user.name}`}><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m14 5 5 5M4 20l4.2-.8L19 8.4a2.1 2.1 0 0 0-3-3L5.2 16.2 4 20Z" /></svg><span className="users-action-tooltip" role="tooltip">Editar</span></summary>
            <form method="POST" action={user.updateUrl} className="users-form">
                <Token method="PUT" />
                <Field name={`name-${user.id}`} label="Nombre">
                    <input id={`name-${user.id}`} name="name" defaultValue={user.name} required maxLength="255" />
                </Field>
                <Field name={`email-${user.id}`} label="Correo">
                    <input id={`email-${user.id}`} name="email" type="email" defaultValue={user.email} required maxLength="255" />
                </Field>
                <Field name={`role-${user.id}`} label="Rol">
                    <select id={`role-${user.id}`} name="role" value={role} onChange={(event) => setRole(event.target.value)}>
                        {roles.map((role) => <option key={role} value={role}>{roleNames[role]}</option>)}
                    </select>
                </Field>
                <AccountControls role={role} permissions={user.permissions} restricted={user.chatRestricted} actions={actions} />
                <Field name={`password-${user.id}`} label="Nueva contraseña (opcional)">
                    <input id={`password-${user.id}`} name="password" type="password" autoComplete="new-password" />
                </Field>
                <Field name={`password-confirmation-${user.id}`} label="Confirmar contraseña">
                    <input id={`password-confirmation-${user.id}`} name="password_confirmation" type="password" autoComplete="new-password" />
                </Field>
                <div className="users-form-actions"><button className="studio-button">Guardar cambios</button></div>
            </form>
        </details>
    );
}

export default function UserManagement({ users, pagination, filters, roles, storeUrl, operatorActions }) {
    const [newRole, setNewRole] = React.useState("usuario");
    return (
        <div className="studio-page users-page">
            <Heading eyebrow="ADMINISTRACIÓN" title="Gestión de usuarios">
                Crea cuentas, asigna permisos y mantén actualizado el acceso a UEBTV.
            </Heading>
            <Feedback />
            <section className="studio-panel users-create">
                <div className="studio-panel-heading"><h2>Crear usuario</h2><span>Contraseña inicial segura</span></div>
                <form method="POST" action={storeUrl} className="users-form">
                    <Token />
                    <Field name="name" label="Nombre completo"><input id="name" name="name" autoComplete="name" required maxLength="255" /></Field>
                    <Field name="email" label="Correo electrónico"><input id="email" name="email" type="email" autoComplete="email" required maxLength="255" /></Field>
                    <Field name="role" label="Rol"><select id="role" name="role" value={newRole} onChange={(event) => setNewRole(event.target.value)}>{roles.map((role) => <option key={role} value={role}>{roleNames[role]}</option>)}</select></Field>
                    <AccountControls role={newRole} actions={operatorActions} />
                    <Field name="password" label="Contraseña"><input id="password" name="password" type="password" autoComplete="new-password" required /></Field>
                    <Field name="password_confirmation" label="Confirmar contraseña"><input id="password_confirmation" name="password_confirmation" type="password" autoComplete="new-password" required /></Field>
                    <div className="users-form-actions"><button className="studio-button">Crear cuenta</button></div>
                    <p className="users-help">Usa al menos 8 caracteres, con mayúsculas, minúsculas, números y símbolos. La cuenta quedará verificada y podrá iniciar sesión de inmediato.</p>
                </form>
            </section>

            <section className="studio-panel studio-management users-list">
                <div className="studio-panel-heading"><h2>Cuentas registradas</h2><span>{pagination.total} usuarios</span></div>
                <form method="GET" className="users-filters">
                    <label>Buscar<input name="q" defaultValue={filters.q} placeholder="Nombre o correo" /></label>
                    <label>Rol<select name="role" defaultValue={filters.role}><option value="">Todos</option>{roles.map((role) => <option key={role} value={role}>{roleNames[role]}</option>)}</select></label>
                    <button className="studio-button studio-button-soft">Filtrar</button>
                </form>
                {users.length ? users.map((user) => (
                    <article className="users-row" key={user.id}>
                        <div className="users-avatar" aria-hidden="true">{user.name.slice(0, 1).toUpperCase()}</div>
                        <div className="users-identity"><h3>{user.name}{user.isCurrent && <small>Tu cuenta</small>}</h3><p>{user.email}</p></div>
                        <span className={`users-role users-role--${user.role}`}>{roleNames[user.role] || user.role}</span>
                        <span className="users-verified">{user.verified ? "Verificada" : "Sin verificar"}</span>
                        <div className="users-actions"><UserEdit user={user} roles={roles} actions={operatorActions} />
                            {!user.isCurrent && !user.hasEvents && !user.hasMessages
                                ? <Action url={user.deleteUrl} method="DELETE" confirm={`¿Eliminar la cuenta de ${user.name}?`} className="studio-delete users-delete-icon" ariaLabel={`Eliminar cuenta de ${user.name}`}><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M10 11v6m4-6v6M6 7l1 13h10l1-13M9 7V4h6v3" /></svg><span className="users-action-tooltip" role="tooltip">Eliminar</span></Action>
                                : <button type="button" className="studio-delete users-delete-disabled users-delete-icon" disabled aria-label={`No se puede eliminar la cuenta de ${user.name}`} title={user.isCurrent ? "No puedes eliminar tu propia cuenta" : "La cuenta tiene eventos o mensajes asociados"}><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M10 11v6m4-6v6M6 7l1 13h10l1-13M9 7V4h6v3" /></svg><span className="users-action-tooltip" role="tooltip">No disponible</span></button>}
                        </div>
                        {user.hasEvents && <p className="users-protected">Conserva eventos asociados.</p>}
                    </article>
                )) : <div className="studio-empty"><h3>No hay usuarios con esos filtros</h3><p>Prueba otro nombre, correo o rol.</p></div>}
                <div className="studio-pagination"><Pagination pagination={pagination} /></div>
            </section>
        </div>
    );
}
