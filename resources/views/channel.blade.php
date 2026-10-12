<x-app-layout>
    {{-- Relative URL: phones must query the same host/proxy used to open /senal. --}}
    <div data-react-page="Channel" data-props="{{ json_encode(['stateUrl' => '/senal/estado']) }}"></div>
</x-app-layout>
