@php
    $recording = $evento->transmision;
    $recordingLabels = ['idle' => 'Aún no se ha iniciado', 'recording' => 'Grabando transmisión',
        'waiting' => 'Esperando cierre o reconexión', 'processing' => 'Preparando video',
        'ready' => 'Disponible bajo demanda', 'failed' => 'No se pudo preparar el video'];
@endphp
<section class="bg-white p-6 rounded-xl border border-gray-200 space-y-4" aria-label="Grabación automática">
    <div class="flex flex-wrap justify-between gap-3">
        <h2 class="text-xl font-bold text-slate-900">Grabación automática</h2>
        <span class="text-sm font-semibold text-blue-700">{{ $recordingLabels[$recording?->recording_status ?? 'idle'] ?? 'Pendiente' }}</span>
    </div>
    <p class="text-sm text-gray-600">El video se guarda desde el inicio y se publica automáticamente al terminar. No requiere aprobación.</p>
    @if ($recording?->recording_status === 'ready')
        <video controls playsinline preload="metadata" class="w-full rounded-lg bg-black" style="max-height:420px" src="{{ route('eventos.recording', $evento) }}"></video>
        <a class="inline-block text-sm text-blue-700 underline" href="{{ route('eventos.show', $evento) }}">Ver grabación en la sala pública →</a>
    @elseif ($recording?->recording_status === 'failed')
        <p role="alert" class="text-sm text-red-700">{{ $recording->recording_error }}</p>
        <p class="text-sm text-gray-600">Los archivos capturados se conservan en el servidor. El sistema hace hasta tres intentos.</p>
        <form method="POST" action="{{ route('streaming.recording.retry', $evento) }}">@csrf
            <button class="rounded-lg bg-blue-700 px-4 py-2 text-sm text-white" type="submit">Reintentar preparación</button>
        </form>
    @elseif (in_array($recording?->recording_status, ['recording', 'waiting', 'processing']))
        <p class="text-sm text-gray-600">Al dejar de recibir señal, se esperan {{ max(15, config('streaming.reconnect_grace')) }} segundos por si OBS se reconecta. Después se prepara el MP4; el tiempo depende de la duración del evento.</p>
    @endif
</section>
