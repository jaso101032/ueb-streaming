<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Editar Evento: ') }} {{ $evento->titulo }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                
                @if ($errors->any())
                    <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative">
                        <ul>
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form action="{{ route('admin.eventos.update', $evento) }}" method="POST">
                    @csrf
                    @method('PUT')

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Título -->
                        <div>
                            <label class="block font-medium text-sm text-gray-700">Título del Evento</label>
                            <input type="text" name="titulo" value="{{ old('titulo', $evento->titulo) }}" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        </div>

                        <!-- Tipo de Evento -->
                        <div>
                            <label class="block font-medium text-sm text-gray-700">Tipo de Evento</label>
                            <select name="tipo_evento" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                @foreach(['Graduaciones', 'Sesiones solemnes', 'Conferencias', 'Seminarios', 'Defensas de tesis', 'Eventos culturales', 'Eventos deportivos', 'Actos institucionales', 'Rendición de cuentas'] as $tipo)
                                    <option value="{{ $tipo }}" {{ old('tipo_evento', $evento->tipo_evento) == $tipo ? 'selected' : '' }}>{{ $tipo }}</option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Categoría -->
                        <div>
                            <label class="block font-medium text-sm text-gray-700">ID Categoría</label>
                            <input type="number" name="categoria_id" value="{{ old('categoria_id', $evento->categoria_id) }}" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        </div>

                        <!-- Estado del Evento -->
                        <div>
                            <label class="block font-medium text-sm text-gray-700">Estado</label>
                            <select name="estado" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="programado" {{ old('estado', $evento->estado) == 'programado' ? 'selected' : '' }}>Programado</option>
                                <option value="en_vivo" {{ old('estado', $evento->estado) == 'en_vivo' ? 'selected' : '' }}>En Vivo</option>
                                <option value="finalizado" {{ old('estado', $evento->estado) == 'finalizado' ? 'selected' : '' }}>Finalizado</option>
                                <option value="cancelado" {{ old('estado', $evento->estado) == 'cancelado' ? 'selected' : '' }}>Cancelado</option>
                            </select>
                        </div>

                        <!-- Fecha y Hora de Inicio -->
                        <div>
                            <label class="block font-medium text-sm text-gray-700">Fecha y Hora de Inicio</label>
                            <input type="datetime-local" name="fecha_inicio" value="{{ old('fecha_inicio', optional($evento->fecha_inicio)->format('Y-m-d\TH:i')) }}" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        </div>

                        <!-- Lugar -->
                        <div>
                            <label class="block font-medium text-sm text-gray-700">Lugar</label>
                            <input type="text" name="lugar" value="{{ old('lugar', $evento->lugar) }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        </div>

                        <!-- Descripción -->
                        <div class="md:col-span-2">
                            <label class="block font-medium text-sm text-gray-700">Descripción</label>
                            <textarea name="descripcion" rows="4" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('descripcion', $evento->descripcion) }}</textarea>
                        </div>

                        <div class="md:col-span-2">
                            <label class="block font-medium text-sm text-gray-700">Video grabado</label>
                            <input type="url" name="video_url" value="{{ old('video_url', $evento->video_url) }}" placeholder="https://.../video.mp4 o enlace del repositorio" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <small class="text-gray-500">Opcional. Enlace al video archivado para la biblioteca UEBTV.</small>
                        </div>
                    </div>

                    <div class="flex items-center justify-end mt-4">
                        <a href="{{ route('admin.eventos.index') }}" class="text-gray-600 underline mr-4">Cancelar</a>
                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                            Actualizar Evento
                        </button>
                    </div>
                </form>

            </div>
        </div>
    </div>
</x-app-layout>