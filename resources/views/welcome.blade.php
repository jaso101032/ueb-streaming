<x-app-layout>
    @php
        $dashboardProps = [
            'name' => Auth::user()->name,
            'role' => Auth::user()->role ?: 'usuario',
            'isAdmin' => Auth::user()->role === 'admin',
            'canControl' => in_array(Auth::user()->role, ['admin', 'operador']),
            'catalogUrl' => route('eventos.index'),
            'adminUrl' => route('admin.dashboard'),
            'streamingUrl' => route('streaming.control'),
            'logoutUrl' => route('logout'),
        ];
    @endphp

    <div id="uebtv-dashboard" data-props='@json($dashboardProps)'></div>
</x-app-layout>