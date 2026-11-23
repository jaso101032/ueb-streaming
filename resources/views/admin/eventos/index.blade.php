<x-app-layout>
    @php
$props = ['events'=>$eventos->map(fn($e)=>\App\Support\ReactPage::event($e,true))->values(), 'pagination'=>\App\Support\ReactPage::pagination($eventos), 'filters'=>$filters ?? [], 'counts'=>$counts ?? [], 'baseUrl'=>route('admin.eventos.index')];
    @endphp
    <div data-react-page="EventList" data-props="{{ json_encode($props, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}"></div>
</x-app-layout>
