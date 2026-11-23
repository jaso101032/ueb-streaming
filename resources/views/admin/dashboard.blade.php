<x-app-layout>
    <div id="uebtv-admin-studio" data-props="{{ json_encode($props) }}"></div>
    <noscript><div class="studio-page"><h1>Administración UEBTV</h1><p>Activa JavaScript para ver el resumen interactivo.</p><a href="{{ route('admin.eventos.index') }}">Eventos</a> · <a href="{{ route('admin.eventos.create') }}">Crear evento</a></div></noscript>
</x-app-layout>
