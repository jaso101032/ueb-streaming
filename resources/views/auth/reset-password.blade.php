<x-app-layout>
    @php
$props=['mode'=>'reset','action'=>route('password.store'),'name'=>old('name',''),'email'=>old('email',request('email','')),'token'=>request()->route('token'),'forgotUrl'=>route('password.request')];
    @endphp
    <div data-react-page="Auth" data-props="{{ json_encode($props, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}"></div>
</x-app-layout>
