<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'UEBTV') }}</title>

        <!-- Fonts -->

        <!-- Scripts y Estilos (Tailwind CSS / Vite) -->
        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.js'], 'uebtv-build-v2')
    </head>
    <body class="font-sans antialiased bg-gray-50 text-gray-900">
        <div class="min-h-screen flex flex-col">
            
            @php
                $operator = auth()->check() && in_array(strtolower(trim((string) auth()->user()->role)), ['admin', 'operador']);
                $links = [['label' => 'Explorar', 'url' => route('eventos.index'), 'active' => request()->routeIs('eventos.*')]];
                if ($operator) {
                    $links = array_merge([
                        ['label' => 'Vista general', 'url' => route('admin.dashboard'), 'active' => request()->routeIs('admin.dashboard')],
                        ['label' => 'Eventos', 'url' => route('admin.eventos.index'), 'active' => request()->routeIs('admin.eventos.*')],
                        ['label' => 'Streaming', 'url' => route('streaming.control'), 'active' => request()->routeIs('streaming.*')],
                    ], $links);
                }
            @endphp
            <a class="studio-skip" href="#contenido">Saltar al contenido</a>
            <header class="studio-header" x-data="{ menu: false }" @keydown.escape.window="menu = false">
                <div class="studio-header-inner">
                    <a class="studio-brand" href="{{ $operator ? route('admin.dashboard') : route('eventos.index') }}" aria-label="UEBTV · Inicio"><img class="brand-mascot" src="{{ asset('images/mascota-ueb-login.png') }}" alt="Mascota de la UEB" width="48" height="64"><span>UEB<span class="studio-brand-tv">TV</span><small>UNIVERSIDAD ESTATAL DE BOLÍVAR</small></span></a>
                    <nav class="studio-desktop-nav" aria-label="Navegación principal">
                        @foreach($links as $link)<a href="{{ $link['url'] }}" @if($link['active']) aria-current="page" @endif>{{ $link['label'] }}</a>@endforeach
                    </nav>
                    <div class="studio-account">
                        @auth
                            <a class="studio-avatar" href="{{ route('profile.edit') }}" aria-label="Editar mi perfil">{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</a>
                            <form method="POST" action="{{ route('logout') }}">@csrf<button class="studio-signout" type="submit"><svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 4H4v16h5M13 8l4 4-4 4M8 12h13"/></svg><span>Cerrar sesión</span></button></form>
                        @else
                            <a class="studio-signout" href="{{ route('login') }}">Ingresar</a>
                        @endauth
                        <button class="studio-menu-toggle" type="button" @click="menu = !menu" :aria-expanded="menu.toString()" aria-controls="studio-mobile-nav" aria-label="Abrir o cerrar menú">☰</button>
                    </div>
                </div>
                <nav id="studio-mobile-nav" class="studio-mobile-nav" x-cloak x-show="menu" aria-label="Navegación móvil">
                    @foreach($links as $link)<a href="{{ $link['url'] }}" @if($link['active']) aria-current="page" @endif>{{ $link['label'] }} <span aria-hidden="true">↗</span></a>@endforeach
                    @guest<a href="{{ route('register') }}">Crear cuenta <span aria-hidden="true">↗</span></a>@endguest
                </nav>
            </header>

            @isset($header)
                <div class="bg-white border-b border-gray-200">
                    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">{{ $header }}</div>
                </div>
            @endisset

            <!-- Contenido Principal -->
            <main id="contenido" class="flex-grow" tabindex="-1">
                {{ $slot }}
            </main>

            <!-- Footer Institucional -->
            <footer class="uebtv-footer">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    &copy; {{ date('Y') }} UEBTV · Universidad Estatal de Bolívar
                </div>
            </footer>

        </div>
    </body>
</html>