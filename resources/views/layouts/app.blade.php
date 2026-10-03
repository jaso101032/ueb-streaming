<!DOCTYPE html>
<html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}"><meta name="theme-color" content="#071a35"><title>UEBTV</title>@viteReactRefresh @vite(['resources/css/app.css','resources/js/app.js'],'uebtv-build-v2')</head>
<body><div id="uebtv-platform" data-shell="{{ json_encode(\App\Support\ReactPage::shell(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}">{{ $slot }}</div><noscript>Activa JavaScript para utilizar UEBTV.</noscript></body></html>
