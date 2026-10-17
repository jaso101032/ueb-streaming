<x-app-layout>
@php($props = ['events'=>$eventos->map(fn($e)=>\App\Support\ReactPage::event($e, auth()->user()?->can('delete-event') ?? false))->values(),'upcoming'=>$proximos->map(fn($e)=>\App\Support\ReactPage::event($e))->values(),'pagination'=>\App\Support\ReactPage::pagination($eventos),'filter'=>$filter,'filters'=>$filters,'categories'=>$categorias,'faculties'=>$facultades,'isAdmin'=>auth()->user()?->role === 'admin'])
<div data-react-page="Catalog" data-props="{{ json_encode($props, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}"></div>
</x-app-layout>
