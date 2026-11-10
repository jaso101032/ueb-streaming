<x-app-layout>
    @php
$values=$evento->only(['titulo','tipo_evento','categoria_id','facultad_id','carrera_id','lugar','estado','descripcion','video_url','visibilidad']);
$values['visibilidad']=$values['visibilidad'] ?? 'publico';
$values['requiere_aprobacion']=(bool)($evento->requiere_aprobacion ?? false);
$values['fecha_inicio']=$evento->fecha_inicio?->format('Y-m-d\TH:i');
$values['fecha_fin']=$evento->fecha_fin?->format('Y-m-d\TH:i');
$values['recording_fps_limit']=$evento->transmision?->recording_fps_limit;
$values['chat_enabled']=$evento->chat_enabled ?? true;
$props=['editing'=>$evento->exists,'action'=>$evento->exists?route('admin.eventos.update',$evento):route('admin.eventos.store'),
 'values'=>array_replace($values,session()->getOldInput()),'types'=>$tipos->values(),'categories'=>$categorias->map->only(['id','nombre'])->values(),
 'faculties'=>$facultades->map->only(['id','nombre'])->values(),'careers'=>$carreras->map->only(['id','nombre','facultad_id'])->values(),
 'image'=>$evento->miniatura?asset('storage/'.$evento->miniatura):null,'back'=>route('admin.eventos.index'),
 'recordingSettingsLocked'=>(bool)$evento->transmision?->iniciado_en,
 'canToggleChat'=>(bool)auth()->user()?->allowsOperatorAction('toggle_chat'),
 'visibilities'=>collect(\App\Models\Evento::VISIBILITIES)->map(fn($nombre,$id)=>['id'=>$id,'nombre'=>$nombre])->values()];
    @endphp
    <div data-react-page="EventForm" data-props="{{ json_encode($props, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}"></div>
</x-app-layout>
