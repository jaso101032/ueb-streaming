<x-app-layout>
    {{-- Relative URL: phones must query the same host/proxy used to open /senal. --}}
    <div data-react-page="Channel" data-props="{{ json_encode(['stateUrl' => '/senal/estado', 'recent' => $recent ?? []], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}"></div>
</x-app-layout>
