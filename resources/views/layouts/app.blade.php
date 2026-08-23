<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'UEBTV') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts y Estilos (Tailwind CSS / Vite) -->
        @vite(['resources/css/app.css', 'resources/js/app.js'], 'uebtv-build')
    </head>
    <body class="font-sans antialiased bg-gray-50 text-gray-900">
        <div class="min-h-screen flex flex-col">
            
            <!-- Navbar Superior Institucional -->
            <header class="uebtv-header">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
                    <div class="flex items-center space-x-3">
                        <span class="uebtv-mark">UEB</span>
                        <a href="{{ route('dashboard') }}" class="font-bold text-lg tracking-tight hover:text-blue-100 transition">
                            UEBTV
                        </a>
                    </div>

                    <div class="flex items-center space-x-4">
                        @auth
                            <div class="flex items-center space-x-3">
                                <span class="text-sm font-medium text-blue-100 hidden sm:inline">{{ Auth::user()->name }}</span>
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="bg-blue-800 hover:bg-blue-700 text-xs text-white px-3 py-2 rounded-lg font-medium transition shadow-sm">
                                        Cerrar Sesión
                                    </button>
                                </form>
                            </div>
                        @endauth
                    </div>
                </div>
            </header>

            <!-- Contenido Principal -->
            <main class="flex-grow">
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