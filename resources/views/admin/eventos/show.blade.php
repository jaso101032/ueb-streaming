<x-app-layout>
    <x-slot name="header"><h1 class="text-2xl font-bold">Detalle del evento</h1></x-slot>
    <div class="max-w-7xl mx-auto px-4 py-8 space-y-6">
        @if (session('success'))<div role="status" class="p-4 bg-green-100 text-green-800 rounded-lg">{{ session('success') }}</div>@endif
        @if ($errors->any())<div role="alert" class="p-4 bg-red-50 text-red-800 rounded-lg">{{ $errors->first() }}</div>@endif
        @include('streaming.finish-button')
        <nav class="flex flex-wrap gap-4" aria-label="Acciones del evento">
            <a class="text-blue-700 underline" href="{{ route('admin.eventos.index') }}">← Volver a eventos</a>
            <a class="text-blue-700 underline" href="{{ route('admin.eventos.edit', $evento) }}">Editar evento</a>
            <a class="text-blue-700 underline" href="{{ route('eventos.show', $evento) }}">Ver sala pública</a>
            <a class="text-blue-700 underline" href="{{ route('streaming.control') }}">Control de streaming</a>
        </nav>
        @include('streaming.recording-status')
        <article class="bg-white p-6 rounded-xl border border-gray-200 space-y-6">
            <h2 class="text-3xl font-bold break-words">{{ $evento->titulo }}</h2>
            @if ($evento->miniatura)<img src="{{ asset('storage/'.$evento->miniatura) }}" alt="Miniatura de {{ $evento->titulo }}" class="rounded-lg w-full" style="max-width:640px;max-height:360px;object-fit:cover">@endif
            <dl class="grid grid-cols-1 md:grid-cols-2 gap-6">
                @foreach (['Estado' => ucfirst(str_replace('_', ' ', $evento->estado)), 'Tipo' => $evento->tipo_evento, 'Categoría' => $evento->categoria?->nombre, 'Organizador' => $evento->organizador?->name, 'Facultad' => $evento->facultad?->nombre ?? 'Toda la institución', 'Carrera' => $evento->carrera?->nombre ?? 'Sin carrera específica', 'Inicio' => $evento->fecha_inicio?->format('d/m/Y H:i'), 'Cierre' => $evento->fecha_fin?->format('d/m/Y H:i') ?? 'Sin definir', 'Lugar' => $evento->lugar ?? 'Sin definir'] as $label => $value)
                    <div><dt class="text-sm text-gray-500">{{ $label }}</dt><dd class="font-semibold break-words">{{ $value }}</dd></div>
                @endforeach
            </dl>
            <div><h3 class="font-semibold">Descripción</h3><p class="whitespace-pre-line break-words text-gray-600">{{ $evento->descripcion ?: 'Sin descripción.' }}</p></div>
            @if ($evento->video_url)<p class="break-all"><strong>Video grabado:</strong> {{ $evento->video_url }}</p>@endif
            <p class="text-sm text-gray-600">{{ $evento->transmision ? 'Clave OBS disponible en el control de streaming.' : 'Este evento todavía no tiene una clave de transmisión.' }}</p>
        </article>
    </div>
</x-app-layout>
