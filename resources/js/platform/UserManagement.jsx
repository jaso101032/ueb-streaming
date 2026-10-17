import React from "react";
import { Action, Feedback, Field, Heading, Pagination, Token } from "./shared";

const roleNames = { admin: "Administrador", operador: "Operador", usuario: "Usuario" };

function UserEdit({ user, roles }) {
    return (
        <details className="users-edit">
            <summary>Editar</summary>
            <form method="POST" action={user.updateUrl} className="users-form">
                <Token method="PUT" />
                <Field name={`name-${user.id}`} label="Nombre">
                    <input id={`name-${user.id}`} name="name" defaultValue={user.name} required maxLength="255" />
                </Field>
                <Field name={`email-${user.id}`} label="Correo">
                    <input id={`email-${user.id}`} name="email" type="email" defaultValue={user.email} required maxLength="255" />
                </Field>
                <Field name={`role-${user.id}`} label="Rol">
                    <select id={`role-${user.id}`} name="role" defaultValue={user.role}>
                        {roles.map((role) => <option key={role} value={role}>{roleNames[role]}</option>)}
                    </select>
                </Field>
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

export default function UserManagement({ users, pagination, filters, roles, storeUrl }) {
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
                    <Field name="role" label="Rol"><select id="role" name="role" defaultValue="usuario">{roles.map((role) => <option key={role} value={role}>{roleNames[role]}</option>)}</select></Field>
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
                        <div className="users-actions"><UserEdit user={user} roles={roles} />
                            {!user.isCurrent && !user.hasEvents && !user.hasMessages && <Action url={user.deleteUrl} method="DELETE" confirm={`¿Eliminar la cuenta de ${user.name}?`} className="studio-delete">Eliminar</Action>}
                        </div>
                        {user.hasEvents && <p className="users-protected">Conserva eventos asociados.</p>}
                    </article>
                )) : <div className="studio-empty"><h3>No hay usuarios con esos filtros</h3><p>Prueba otro nombre, correo o rol.</p></div>}
                <div className="studio-pagination"><Pagination pagination={pagination} /></div>
            </section>
        </div>
    );
}
