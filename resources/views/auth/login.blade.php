<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Acceso al Sistema - Plataforma de Streaming</title>
    <!-- Google Fonts Plus Jakarta Sans -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
    </style>
</head>
<body class="bg-gradient-to-tr from-indigo-100 via-slate-50 to-blue-100 text-slate-800 min-h-screen flex items-center justify-center p-6 relative overflow-hidden selection:bg-indigo-600 selection:text-white">

    <!-- Formas difuminadas de fondo para dar profundidad y dinamismo visual -->
    <div class="absolute -top-32 -left-32 w-96 h-96 bg-indigo-400/20 rounded-full blur-[100px] pointer-events-none"></div>
    <div class="absolute -bottom-32 -right-32 w-96 h-96 bg-blue-400/20 rounded-full blur-[100px] pointer-events-none"></div>

    <!-- Contenedor Principal -->
    <div class="w-full max-w-[440px] relative z-10">
        
        <!-- Logotipo y Encabezado llamativo -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-gradient-to-tr from-indigo-600 to-blue-600 text-white shadow-xl shadow-indigo-500/25 mb-4 ring-4 ring-indigo-600/10 transform hover:scale-105 transition-transform duration-300">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                </svg>
            </div>
            <h1 class="text-2xl font-extrabold tracking-tight text-slate-900">Plataforma de Streaming</h1>
            <p class="text-xs font-bold text-indigo-600 tracking-wider uppercase mt-1.5 flex items-center justify-center gap-1.5">
                <span class="w-2 h-2 rounded-full bg-indigo-600 animate-pulse"></span>
                Centro de Control Institucional
            </p>
        </div>

        <!-- Tarjeta del Formulario con efecto Glassmorphism y sombras ricas -->
        <div class="bg-white/80 backdrop-blur-2xl rounded-3xl shadow-2xl shadow-indigo-900/10 border border-white/80 p-8 sm:p-10">
            
            <div class="mb-6">
                <h2 class="text-lg font-bold text-slate-900">Iniciar Sesión</h2>
                <p class="text-xs text-slate-500 mt-0.5">Ingresa tus credenciales institucionales</p>
            </div>

            <!-- Estado de sesión / Alertas -->
            @if (session('status'))
                <div class="mb-5 text-xs font-medium text-emerald-700 bg-emerald-50 border border-emerald-200 p-3.5 rounded-xl shadow-sm">
                    {{ session('status') }}
                </div>
            @endif

            <form method="POST" action="{{ route('login') }}" class="space-y-5">
                @csrf

                <!-- Correo Electrónico -->
                <div>
                    <label for="email" class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-2">Correo Electrónico</label>
                    <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username" 
                        class="w-full px-4 py-3.5 rounded-xl bg-slate-50/80 border border-slate-200 focus:border-indigo-600 focus:bg-white focus:ring-4 focus:ring-indigo-600/10 text-sm text-slate-800 placeholder-slate-400 transition-all outline-none"
                        placeholder="admin@ueb.edu.ec">
                    @error('email')
                        <span class="text-xs text-red-600 mt-1.5 block font-semibold">{{ $message }}</span>
                    @enderror
                </div>

                <!-- Contraseña -->
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <label for="password" class="block text-xs font-bold uppercase tracking-wider text-slate-600">Contraseña</label>
                        @if (Route::has('password.request'))
                            <a class="text-xs text-indigo-600 hover:text-indigo-800 font-semibold transition-colors" href="{{ route('password.request') }}">
                                ¿Olvidaste tu contraseña?
                            </a>
                        @endif
                    </div>
                    <input id="password" type="password" name="password" required autocomplete="current-password" 
                        class="w-full px-4 py-3.5 rounded-xl bg-slate-50/80 border border-slate-200 focus:border-indigo-600 focus:bg-white focus:ring-4 focus:ring-indigo-600/10 text-sm text-slate-800 placeholder-slate-400 transition-all outline-none"
                        placeholder="••••••••••••">
                    @error('password')
                        <span class="text-xs text-red-600 mt-1.5 block font-semibold">{{ $message }}</span>
                    @enderror
                </div>

                <!-- Recuérdame -->
                <div class="flex items-center pt-1">
                    <input id="remember_me" type="checkbox" name="remember" class="w-4 h-4 text-indigo-600 bg-slate-100 border-slate-300 rounded focus:ring-indigo-500 cursor-pointer">
                    <label for="remember_me" class="ml-2.5 block text-xs text-slate-600 font-medium cursor-pointer">Recordar sesión</label>
                </div>

                <!-- Botón de Ingreso con Degradado Atractivo -->
                <div class="pt-2">
                    <button type="submit" class="w-full py-3.5 px-4 bg-gradient-to-r from-indigo-600 to-blue-600 hover:from-indigo-500 hover:to-blue-500 active:scale-[0.99] text-white font-bold text-sm rounded-xl shadow-lg shadow-indigo-600/30 transition-all duration-200">
                        Acceder al Sistema
                    </button>
                </div>
            </form>
        </div>

        <!-- Pie de página discreto -->
        <div class="mt-8 text-center text-xs text-slate-500 font-medium">
            © {{ date('Y') }} Sistema de Streaming Institucional • Todos los derechos reservados.
        </div>

    </div>

</body>
</html>