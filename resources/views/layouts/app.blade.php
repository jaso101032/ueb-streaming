<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Plataforma de Streaming') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts y Estilos (Tailwind CSS / Vite) -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased bg-gray-50 text-gray-900">
        <div class="min-h-screen flex flex-col">
            
            <!-- Navbar Superior Institucional -->
            <header class="bg-blue-900 text-white shadow-md sticky top-0 z-50">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
                    <div class="flex items-center space-x-3">
                        <span class="bg-blue-800 text-blue-200 text-xs font-bold px-2.5 py-1 rounded-md uppercase tracking-wider">UEB Oficial</span>
                        <a href="{{ route('dashboard') }}" class="font-bold text-lg tracking-tight hover:text-blue-100 transition">
                            Plataforma de Streaming
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
            <footer class="bg-white border-t border-gray-100 py-6 text-center text-xs text-gray-500">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    &copy; {{ date('Y') }} Sistema de Streaming Institucional &bull; Todos los derechos reservados.
                </div>
            </footer>

        </div>
    </body>
</html>