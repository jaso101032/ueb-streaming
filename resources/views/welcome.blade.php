<x-app-layout>
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <!-- Tarjeta de Bienvenida Minimalista con accesos por rol -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-2xl border border-gray-100 p-8 mb-8 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <div>
                    <span class="text-xs font-bold text-indigo-600 uppercase tracking-widest">Portal Institucional - UEB</span>
                    <h2 class="text-2xl font-bold text-gray-900 mt-1">Bienvenido, {{ Auth::user()->name }}</h2>
                    <p class="text-gray-500 text-sm mt-1">Rol actual: <span class="uppercase font-semibold text-gray-700">{{ Auth::user()->role }}</span>. Selecciona una opción para gestionar o visualizar las transmisiones.</p>
                </div>
                
                <div class="flex items-center gap-3 flex-wrap">
                    @if(Auth::user()->role === 'admin')
                        <a href="{{ route('admin.dashboard') }}" class="bg-emerald-600 hover:bg-emerald-500 text-white text-sm font-semibold px-4 py-2.5 rounded-xl transition shadow-sm">
                            Panel Administrador
                        </a>
                    @endif

                    @if(Auth::user()->role === 'admin' || Auth::user()->role === 'operador')
                        <a href="{{ route('streaming.control') }}" class="bg-amber-600 hover:bg-amber-500 text-white text-sm font-semibold px-4 py-2.5 rounded-xl transition shadow-sm">
                            Control de Streaming
                        </a>
                    @endif

                    <form method="POST" action="{{ route('logout') }}" class="inline">
                        @csrf
                        <button type="submit" class="bg-rose-50 hover:bg-rose-100 text-rose-600 text-sm font-semibold px-4 py-2.5 rounded-xl transition">
                            Cerrar Sesión
                        </button>
                    </form>
                </div>
            </div>

            <!-- Sección de Transmisiones Disponibles -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-2xl border border-gray-100 p-8">
                <h3 class="text-lg font-bold text-gray-900 mb-4">Transmisión en Directo</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="border border-gray-100 rounded-2xl p-5 hover:shadow-md transition flex flex-col justify-between">
                        <div>
                            <span class="bg-rose-100 text-rose-700 text-xs font-bold px-2.5 py-1 rounded-md uppercase">En Vivo</span>
                            <h4 class="text-lg font-bold text-gray-900 mt-3">Conferencia Inaugural UEB 2026</h4>
                            <p class="text-gray-500 text-sm mt-1">Accede a la sala de transmisión oficial con reproductor integrado.</p>
                        </div>
                        <div class="mt-6">
                            <a href="{{ url('/eventos/6') }}" class="inline-block bg-blue-900 hover:bg-blue-800 text-white text-sm font-medium px-5 py-2.5 rounded-xl transition shadow-sm">
                                Entrar a la Transmisión &rarr;
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>