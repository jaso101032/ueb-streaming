<x-app-layout>
    @php
$props = ['event'=>\App\Support\ReactPage::event($evento), 'initialReady'=>(bool)$evento->transmision?->recordingPath(), 'videoViews'=>$videoViews, 'messages'=>$chatMessages, 'canModerate'=>$canModerate, 'related'=>$eventosRelacionados->map(fn($e)=>\App\Support\ReactPage::event($e))->values(), 'playlist'=>$playlist ? ['title'=>$playlist['title'], 'url'=>$playlist['url'], 'items'=>$playlist['items']->map(fn($e)=>\App\Support\ReactPage::event($e))->values()] : null, 'guide'=>\App\Support\ProgramGuide::for(auth()->user())];
    @endphp
    <div data-react-page="Watch" data-props="{{ json_encode($props, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}"></div>
</x-app-layout>
