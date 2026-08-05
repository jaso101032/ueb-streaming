<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Panel de Control de Transmisiones (OBS / RTMP)') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            
            @if (session('success'))
                <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
                    <span class="block sm:inline">{{ session('success') }}</span>
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    
                    <!-- Encabezado con título y botón corregido con estilos directos -->
                    <div class="flex flex-col md:flex-row justify-between items-center mb-6 pb-4 border-b border-gray-200 gap-4">
                        <h3 class="text-lg font-medium text-gray-900">Eventos Institucionales y Credenciales de Streaming</h3>
                        
                        <a href="{{ route('admin.eventos.index') }}" style="background-color: #4f46e5; color: #ffffff !important; padding: 10px 20px; border-radius: 6px; font-weight: bold; font-size: 14px; text-decoration: none; display: inline-block;">
                            Gestionar Eventos
                        </a>
                    </div>
                    
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Evento</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Facultad</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Estado</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Credenciales RTMP (OBS)</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @forelse($eventos as $evento)
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm font-medium text-gray-900">{{ $evento->titulo }}</div>
                                            <div class="text-xs text-gray-500">Inicia: {{ $evento->fecha_inicio ? $evento->fecha_inicio->format('d/m/Y H:i') : 'Por definir' }}</div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            {{ $evento->facultad->nombre ?? 'General UEB' }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            @if($evento->estado == 'en_vivo')
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800 animate-pulse">
                                                    En Vivo
                                                </span>
                                            @elseif($evento->estado == 'programado')
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                                    Programado
                                                </span>
                                            @else
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">
                                                    Finalizado
                                                </span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-500">
                                            @if($evento->transmision)
                                                <div class="text-xs space-y-1">
                                                    <div><strong>Servidor:</strong> <code class="bg-gray-100 p-1 rounded">{{ $evento->transmision->rtmp_server }}</code></div>
                                                    <div><strong>Clave:</strong> <code class="bg-gray-100 p-1 rounded">{{ $evento->transmision->stream_key }}</code></div>
                                                </div>
                                            @else
                                                <span class="text-xs text-amber-600 font-medium">Sin clave generada</span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <form action="{{ route('streaming.generar.clave', $evento->id) }}" method="POST">
                                                @csrf
                                                <button type="submit" class="text-indigo-600 hover:text-indigo-900 bg-indigo-50 hover:bg-indigo-100 px-3 py-1 rounded transition text-xs font-semibold">
                                                    {{ $evento->transmision ? 'Regenerar Clave' : 'Generar Clave OBS' }}
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="px-6 py-4 text-center text-sm text-gray-500">
                                            No hay eventos registrados en el sistema todavía.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-4">
                        {{ $eventos->links() }}
                    </div>

                </div>
            </div>

        </div>
    </div>
</x-app-layout>