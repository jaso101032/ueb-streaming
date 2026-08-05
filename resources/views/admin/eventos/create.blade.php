<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Crear Nuevo Evento / Transmisión') }}
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

                <form action="{{ route('admin.eventos.store') }}" method="POST">
                    @csrf

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Título -->
                        <div>
                            <label class="block font-medium text-sm text-gray-700">Título del Evento</label>
                            <input type="text" name="titulo" value="{{ old('titulo') }}" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        </div>

                        <!-- Tipo de Evento -->
                        <div>
                            <label class="block font-medium text-sm text-gray-700">Tipo de Evento</label>
                            <select name="tipo_evento" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="Graduaciones">Graduaciones</option>
                                <option value="Sesiones solemnes">Sesiones solemnes</option>
                                <option value="Conferencias">Conferencias</option>
                                <option value="Seminarios">Seminarios</option>
                                <option value="Defensas de tesis">Defensas de tesis</option>
                                <option value="Eventos culturales">Eventos culturales</option>
                                <option value="Eventos deportivos">Eventos deportivos</option>
                                <option value="Actos institucionales">Actos institucionales</option>
                            </select>
                        </div>

                        <!-- Categoría -->
                        <div>
                            <label class="block font-medium text-sm text-gray-700">Categoría</label>
                            <input type="number" name="categoria_id" placeholder="ID Categoría (ej: 1)" value="{{ old('categoria_id') }}" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <small class="text-gray-500">*(Temporal hasta conectar los selects de categorías)*</small>
                        </div>

                        <!-- Fecha y Hora de Inicio -->
                        <div>
                            <label class="block font-medium text-sm text-gray-700">Fecha y Hora de Inicio</label>
                            <input type="datetime-local" name="fecha_inicio" value="{{ old('fecha_inicio') }}" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        </div>

                        <!-- Lugar -->
                        <div>
                            <label class="block font-medium text-sm text-gray-700">Lugar</label>
                            <input type="text" name="lugar" value="{{ old('lugar') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        </div>

                        <!-- Descripción -->
                        <div class="md:col-span-2">
                            <label class="block font-medium text-sm text-gray-700">Descripción</label>
                            <textarea name="descripcion" rows="4" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('descripcion') }}</textarea>
                        </div>
                    </div>

                    <div class="flex items-center justify-end mt-4">
                        <a href="{{ route('admin.eventos.index') }}" class="text-gray-600 underline mr-4">Cancelar</a>
                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                            Guardar y Generar Clave OBS
                        </button>
                    </div>
                </form>

            </div>
        </div>
    </div>
</x-app-layout>