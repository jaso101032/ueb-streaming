<x-app-layout>
    <div class="py-10 bg-gradient-to-br from-gray-50 via-gray-100 to-indigo-50/30 min-h-screen">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">
            
            <!-- Encabezado de la Sección -->
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
                <div>
                    <span class="px-3 py-1 text-xs font-bold rounded-full bg-indigo-50 text-indigo-700 border border-indigo-100 uppercase tracking-wider">
                        Panel de Administración
                    </span>
                    <h1 class="text-2xl font-black text-gray-900 mt-2">Gestión y Control de Streaming</h1>
                    <p class="text-sm text-gray-500 mt-0.5">Administra los eventos institucionales y obtén las credenciales dinámicas para OBS.</p>
                </div>
                <div class="flex items-center space-x-3">
                    <span class="inline-flex items-center px-3 py-1 rounded-xl text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-100">
                        <span class="w-2 h-2 mr-1.5 bg-emerald-500 rounded-full animate-pulse"></span>
                        Sistema Operativo · IP actualizada al cargar
                    </span>
                </div>
            </div>

            <!-- Tabla de Eventos Estilizada -->
            <div class="bg-white rounded-2xl shadow-xl shadow-gray-100 border border-gray-100 overflow-hidden">
                <div class="p-6 border-b border-gray-100 flex justify-between items-center">
                    <h3 class="text-lg font-bold text-gray-900 flex items-center space-x-2">
                        <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                        </svg>
                        <span>Listado de Eventos Activos</span>
                    </h3>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-gray-50/80 border-b border-gray-100 text-xs font-bold text-gray-400 uppercase tracking-wider">
                                <th class="px-6 py-4">Evento</th>
                                <th class="px-6 py-4">Estado</th>
                                <th class="px-6 py-4">Credenciales OBS (Dinámicas)</th>
                                <th class="px-6 py-4 text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 text-sm">
                            @forelse($eventos as $evento)
                                <tr class="hover:bg-indigo-50/30 transition-colors group">
                                    
                                    <!-- Título y Categoría -->
                                    <td class="px-6 py-4">
                                        <div class="font-bold text-gray-900 group-hover:text-indigo-600 transition-colors">{{ $evento->titulo }}</div>
                                        <div class="text-xs font-medium text-gray-400 mt-0.5">{{ $evento->categoria->nombre ?? 'General' }}</div>
                                    </td>

                                    <!-- Estado -->
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        @if(strtolower($evento->estado) == 'en_vivo' || strtolower($evento->estado) == 'live')
                                            <span class="inline-flex items-center space-x-1.5 px-3 py-1 text-xs font-bold rounded-full bg-red-50 text-red-600 border border-red-100 shadow-sm">
                                                <span class="w-2 h-2 bg-red-500 rounded-full animate-ping"></span>
                                                <span>EN VIVO</span>
                                            </span>
                                        @else
                                            <span class="px-3 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-600">
                                                {{ ucfirst(strtolower($evento->estado)) }}
                                            </span>
                                        @endif
                                    </td>

                                    <!-- Credenciales OBS con IP Dinámica -->
                                    <td class="px-6 py-4">
                                        @if($evento->transmision)
                                            <div class="space-y-1.5 text-xs bg-gray-50 p-3.5 rounded-xl border border-gray-200/80 shadow-inner font-mono">
                                                <div>
                                                    <span class="font-sans font-semibold text-gray-500">Servidor:</span> 
                                                    <span id="obsServerAddress" class="text-indigo-600 font-bold select-all">rtmp://{{ request()->getHost() }}:1935/live</span>
                                                </div>
                                                <div>
                                                    <span class="font-sans font-semibold text-gray-500">Clave:</span> 
                                                    <span class="text-purple-600 font-bold select-all">{{ $evento->transmision->stream_key }}</span>
                                                </div>
                                            </div>
                                        @else
                                            <span class="text-xs font-medium text-amber-600 bg-amber-50 px-3 py-1 rounded-full border border-amber-200">
                                                Sin clave generada
                                            </span>
                                        @endif
                                    </td>

                                    <!-- Acciones -->
                                    <td class="px-6 py-4 text-center whitespace-nowrap">
                                        <form action="{{ route('streaming.generar.clave', $evento->id) }}" method="POST">
                                            @csrf
                                            <button type="submit" class="inline-flex items-center space-x-2 bg-indigo-50 hover:bg-indigo-600 text-indigo-700 hover:text-white text-xs font-bold px-4 py-2.5 rounded-xl transition-all duration-200 border border-indigo-100 shadow-sm hover:shadow">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                                </svg>
                                                <span>{{ $evento->transmision ? 'Regenerar Clave' : 'Generar Clave OBS' }}</span>
                                            </button>
                                        </form>
                                    </td>

                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-6 py-12 text-center text-gray-400 font-medium">
                                        No hay eventos registrados en el sistema todavía.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

            </div>

        </div>
    </div>
</x-app-layout>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const serverAddress = document.getElementById('obsServerAddress');
        if (serverAddress) {
            serverAddress.textContent = `rtmp://${window.location.hostname}:1935/live`;
        }
    });
</script>