<x-app-layout>
    @php
$props = ['event'=>\App\Support\ReactPage::event($evento), 'initialReady'=>(bool)$evento->transmision?->recordingPath(), 'messages'=>$chatMessages, 'canModerate'=>$canModerate, 'related'=>$eventosRelacionados->map(fn($e)=>\App\Support\ReactPage::event($e))->values()];
    @endphp
    <div data-react-page="Watch" data-props="{{ json_encode($props, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}"></div>
</x-app-layout>
