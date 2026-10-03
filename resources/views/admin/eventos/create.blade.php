<x-app-layout>
    @php
$values=$evento->only(['titulo','tipo_evento','categoria_id','facultad_id','carrera_id','lugar','estado','descripcion','video_url']);
$values['fecha_inicio']=$evento->fecha_inicio?->format('Y-m-d\TH:i');
$values['fecha_fin']=$evento->fecha_fin?->format('Y-m-d\TH:i');
$props=['editing'=>$evento->exists,'action'=>$evento->exists?route('admin.eventos.update',$evento):route('admin.eventos.store'),
 'values'=>array_replace($values,session()->getOldInput()),'types'=>$tipos->values(),'categories'=>$categorias->map->only(['id','nombre'])->values(),
 'faculties'=>$facultades->map->only(['id','nombre'])->values(),'careers'=>$carreras->map->only(['id','nombre','facultad_id'])->values(),
 'image'=>$evento->miniatura?asset('storage/'.$evento->miniatura):null,'back'=>route('admin.eventos.index')];
    @endphp
    <div data-react-page="EventForm" data-props="{{ json_encode($props, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}"></div>
</x-app-layout>
