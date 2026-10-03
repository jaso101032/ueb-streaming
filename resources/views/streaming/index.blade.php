<x-app-layout>
    @php
$props = ['events'=>$eventos->map(fn($e)=>\App\Support\ReactPage::event($e,true))->values(), 'pagination'=>\App\Support\ReactPage::pagination($eventos)];
    @endphp
    <div data-react-page="Streaming" data-props="{{ json_encode($props, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}"></div>
</x-app-layout>
