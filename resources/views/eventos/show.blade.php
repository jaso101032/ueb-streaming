<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ $evento->titulo }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                
                <!-- Reproductor Principal con URL Forzada por IP y Menor Retraso -->
                <div class="lg:col-span-2 space-y-4">
                    <div class="bg-black overflow-hidden shadow-sm sm:rounded-lg aspect-video flex items-center justify-center relative">
                        @php
                            // URL apuntando directamente a la IP correcta y al archivo HLS de prueba de OBS
                            $streamUrl = 'http://10.219.229.14/hls/ueb_test_123.m3u8';
                        @endphp

                        @if($streamUrl)
                            <video id="uebVideoPlayer" class="w-full h-full object-contain" controls autoplay muted></video>

                            <script src="https://cdn.jsdelivr.net/npm/hls.js@latest"></script>
                            <script>
                                document.addEventListener('DOMContentLoaded', function () {
                                    var video = document.getElementById('uebVideoPlayer');
                                    var videoSrc = "{{ $streamUrl }}";

                                    if (Hls.isSupported()) {
                                        var hls = new Hls({
                                            liveSyncDurationCount: 1.5, // Reduce la distancia al borde en vivo
                                            maxLiveSyncPlaybackRate: 1.2, // Acelera ligeramente si se acumula retraso para atrapar el directo
                                            enableWorker: true
                                        });

                                        hls.loadSource(videoSrc);
                                        hls.attachMedia(video);
                                        hls.on(Hls.Events.MANIFEST_PARSED, function() {
                                            video.play().catch(function(e) {
                                                console.log("Play interactivo requerido.");
                                            });
                                        });
                                    } else if (video.canPlayType('application/vnd.apple.mpegurl')) {
                                        video.src = videoSrc;
                                        video.addEventListener('loadedmetadata', function() {
                                            video.play();
                                        });
                                    }
                                });
                            </script>
                        @else
                            <div class="text-center p-8 text-white">
                                <p class="text-lg font-medium">Transmisión no disponible temporalmente</p>
                            </div>
                        @endif
                    </div>

                    <!-- Detalles del Evento -->
                    <div class="bg-white p-6 rounded-lg shadow-sm">
                        <div class="flex items-center justify-between mb-4">
                            <span class="px-3 py-1 text-xs font-semibold rounded-full bg-indigo-100 text-indigo-800">
                                {{ $evento->categoria->nombre ?? 'Institucional' }}
                            </span>
                            <span class="text-sm text-gray-500">
                                Facultad: {{ $evento->facultad->nombre ?? 'General UEB' }}
                            </span>
                        </div>
                        <h1 class="text-2xl font-bold text-gray-900 mb-2">{{ $evento->titulo }}</h1>
                        <p class="text-gray-700 leading-relaxed">{{ $evento->descripcion }}</p>
                    </div>
                </div>

                <!-- Panel Lateral -->
                <div class="space-y-6">
                    <div class="bg-white p-6 rounded-lg shadow-sm">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Información de la Sesión</h3>
                        <ul class="space-y-3 text-sm text-gray-600">
                            <li class="flex justify-between">
                                <span class="font-medium">Estado:</span>
                                <span class="uppercase font-bold text-indigo-600">{{ $evento->estado }}</span>
                            </li>
                            <li class="flex justify-between">
                                <span class="font-medium">Organizador:</span>
                                <span>{{ $evento->organizador->name ?? 'Universidad Estatal de Bolívar' }}</span>
                            </li>
                        </ul>
                        <div class="mt-6 pt-6 border-t border-gray-100">
                            <a href="{{ route('eventos.index') }}" class="w-full block text-center bg-gray-100 hover:bg-gray-200 text-gray-800 font-semibold py-2 px-4 rounded transition text-sm">
                                ← Volver al Catálogo de Eventos
                            </a>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</x-app-layout>