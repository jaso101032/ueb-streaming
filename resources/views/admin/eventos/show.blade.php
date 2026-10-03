<x-app-layout>
    @php
$props = ['event'=>\App\Support\ReactPage::event($evento,true)];
    @endphp
    <div data-react-page="EventDetail" data-props="{{ json_encode($props, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}"></div>
</x-app-layout>
