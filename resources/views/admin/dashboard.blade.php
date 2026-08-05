<x-app-layout>
    <x-slot name="header">
        Panel de Administración General
    </x-slot>

    <div class="space-y-6">
        
        <!-- Tarjetas de Estadísticas Principales -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
            
            <!-- Usuarios -->
            <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Total Usuarios</p>
                    <h3 class="text-2xl font-extrabold text-blue-900 mt-1">{{ $totalUsuarios }}</h3>
                </div>
                <div class="w-12 h-12 bg-blue-50 text-blue-900 rounded-xl flex items-center justify-center font-bold">
                    👥
                </div>
            </div>

            <!-- Eventos -->
            <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Eventos Registrados</p>
                    <h3 class="text-2xl font-extrabold text-blue-900 mt-1">{{ $totalEventos }}</h3>
                </div>
                <div class="w-12 h-12 bg-blue-50 text-blue-900 rounded-xl flex items-center justify-center font-bold">
                    📅
                </div>
            </div>

            <!-- En Vivo -->
            <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Eventos En Vivo</p>
                    <h3 class="text-2xl font-extrabold text-red-600 mt-1">{{ $eventosEnVivo }}</h3>
                </div>
                <div class="w-12 h-12 bg-red-50 text-red-600 rounded-xl flex items-center justify-center font-bold animate-pulse">
                    🔴
                </div>
            </div>

            <!-- Servidor OBS -->
            <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Streams Activos</p>
                    <h3 class="text-2xl font-extrabold text-blue-900 mt-1">{{ $transmisionesActivas }}</h3>
                </div>
                <div class="w-12 h-12 bg-blue-50 text-blue-900 rounded-xl flex items-center justify-center font-bold">
                    📡
                </div>
            </div>

        </div>

        <!-- Tabla Reciente de Eventos Institucionales -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="p-6 border-b border-gray-100 flex items-center justify-between">
                <h3 class="font-bold text-gray-900 text-lg">Últimos Eventos Registrados</h3>
                <a href="{{ route('streaming.control') }}" class="text-xs font-semibold bg-blue-900 text-white px-4 py-2 rounded-xl hover:bg-blue-800 transition">
                    Ir al Panel de Streaming
                </a>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-sm">
                    <thead>
                        <tr class="bg-gray-50 text-gray-600 uppercase text-xs tracking-wider border-b border-gray-100">
                            <th class="py-3 px-6">Título del Evento</th>
                            <th class="py-3 px-6">Facultad</th>
                            <th class="py-3 px-6">Estado</th>
                            <th class="py-3 px-6">Organizador</th>
                            <th class="py-3 px-6 text-right">Acción</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-gray-700">
                        @forelse($eventosRecientes as $evento)
                            <tr class="hover:bg-blue-50/50 transition">
                                <td class="py-4 px-6 font-semibold text-gray-900">{{ $evento->titulo }}</td>
                                <td class="py-4 px-6">{{ $evento->facultad->siglas ?? 'UEB' }}</td>
                                <td class="py-4 px-6">
                                    <span class="px-2.5 py-1 text-xs font-bold rounded-full 
                                        {{ $evento->estado === 'en_vivo' ? 'bg-red-100 text-red-700' : 'bg-blue-100 text-blue-800' }}">
                                        {{ ucfirst($evento->estado) }}
                                    </span>
                                </td>
                                <td class="py-4 px-6">{{ $evento->organizador->name ?? 'N/D' }}</td>
                                <td class="py-4 px-6 text-right">
                                    <a href="{{ url('/eventos/' . $evento->id) }}" class="text-blue-600 hover:text-blue-900 font-semibold">Ver</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-6 text-center text-gray-500">No hay eventos registrados en el sistema.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</x-app-layout>