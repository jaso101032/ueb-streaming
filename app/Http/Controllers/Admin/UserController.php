<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\ReactPage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class UserController extends Controller
{
    private const ROLES = ['admin', 'operador', 'usuario'];

    public function index(Request $request): View
    {
        $query = User::query()->orderBy('name');
        $search = trim((string) $request->query('q', ''));
        $role = (string) $request->query('role', '');

        if ($search !== '') {
            $query->where(fn ($builder) => $builder
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%"));
        }
        if (in_array($role, self::ROLES, true)) {
            $query->where('role', $role);
        }

        $users = $query->paginate(15)->withQueryString();
        $props = [
            'users' => $users->getCollection()->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => in_array(strtolower(trim((string) $user->role)), ['user', 'usuario', ''], true) ? 'usuario' : $user->role,
                'permissions' => $user->isOperador() ? ($user->operator_permissions ?? User::operatorActions()) : [],
                'chatRestricted' => (bool) $user->chat_restricted,
                'verified' => $user->email_verified_at !== null,
                'isCurrent' => $user->is(auth()->user()),
                'hasEvents' => DB::table('eventos')->where('organizador_id', $user->id)->exists(),
                'hasMessages' => DB::table('chat_messages')->where('user_id', $user->id)->exists(),
                'updateUrl' => route('admin.usuarios.update', $user),
                'deleteUrl' => route('admin.usuarios.destroy', $user),
            ])->values(),
            'pagination' => ReactPage::pagination($users),
            'filters' => ['q' => $search, 'role' => $role],
            'roles' => self::ROLES,
            'operatorActions' => User::operatorActions(),
            'storeUrl' => route('admin.usuarios.store'),
        ];

        return view('admin.usuarios.index', compact('props'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', Rule::in(self::ROLES)],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string', Rule::in(User::operatorActions())],
            'permissions_present' => ['sometimes', 'boolean'],
            'chat_restricted' => ['sometimes', 'boolean'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->mixedCase()->numbers()->symbols()],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'role' => $data['role'],
            'operator_permissions' => $data['role'] === 'operador' ? array_values(array_unique($data['permissions'] ?? [])) : null,
            'chat_restricted' => (bool) ($data['chat_restricted'] ?? false),
            'password' => $data['password'],
        ]);
        $user->forceFill(['email_verified_at' => now()])->save();

        return to_route('admin.usuarios.index')->with('success', 'Usuario creado. Comparte la contraseña inicial de forma segura.');
    }

    public function update(Request $request, User $usuario): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique('users', 'email')->ignore($usuario->id)],
            'role' => ['required', Rule::in(self::ROLES)],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string', Rule::in(User::operatorActions())],
            'permissions_present' => ['sometimes', 'boolean'],
            'chat_restricted' => ['sometimes', 'boolean'],
            'password' => ['nullable', 'confirmed', Password::min(8)->letters()->mixedCase()->numbers()->symbols()],
        ]);

        if ($usuario->is(auth()->user()) && $data['role'] !== 'admin') {
            return back()->withErrors(['role' => 'No puedes cambiar tu propio rol de administrador.'])->withInput($request->except('password', 'password_confirmation'));
        }

        if ($usuario->isAdmin() && $data['role'] !== 'admin' && User::where('role', 'admin')->count() <= 1) {
            return back()->withErrors(['role' => 'Debe quedar al menos un administrador activo.'])->withInput($request->except('password', 'password_confirmation'));
        }

        $wasOperator = $usuario->isOperador();
        $usuario->name = $data['name'];
        $usuario->email = $data['email'];
        $usuario->role = $data['role'];
        $usuario->operator_permissions = $data['role'] === 'operador'
            ? (isset($data['permissions_present']) || isset($data['permissions']) || ! $wasOperator
                ? array_values(array_unique($data['permissions'] ?? []))
                : $usuario->operator_permissions)
            : null;
        if (array_key_exists('chat_restricted', $data)) {
            $usuario->chat_restricted = (bool) $data['chat_restricted'];
        }
        if (! empty($data['password'])) {
            $usuario->password = $data['password'];
        }
        $usuario->save();

        return to_route('admin.usuarios.index')->with('success', 'Usuario actualizado.');
    }

    public function destroy(User $usuario): RedirectResponse
    {
        if ($usuario->is(auth()->user())) {
            return back()->withErrors(['user' => 'No puedes eliminar tu propia cuenta desde esta pantalla.']);
        }
        if (DB::table('eventos')->where('organizador_id', $usuario->id)->exists()) {
            return back()->withErrors(['user' => 'No se puede eliminar esta cuenta porque tiene eventos asociados.']);
        }
        if (DB::table('chat_messages')->where('user_id', $usuario->id)->exists()) {
            return back()->withErrors(['user' => 'No se puede eliminar esta cuenta porque tiene mensajes asociados.']);
        }
        if ($usuario->isAdmin() && User::where('role', 'admin')->count() <= 1) {
            return back()->withErrors(['user' => 'Debe quedar al menos un administrador activo.']);
        }

        $usuario->delete();

        return to_route('admin.usuarios.index')->with('success', 'Usuario eliminado.');
    }
}
