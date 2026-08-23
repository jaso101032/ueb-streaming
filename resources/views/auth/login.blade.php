<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Acceso a UEBTV</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'], 'uebtv-build')
</head>
<body class="uebtv-login-body">
    <main class="uebtv-login-shell">
        <section class="uebtv-login-brand">
            <div class="uebtv-login-brand__top"><span class="uebtv-login-logo">UEB</span><span class="uebtv-login-brand__name">UEBTV</span></div>
            <div class="uebtv-login-brand__message"><p class="uebtv-kicker"><span></span> Señal institucional</p><h1>La Universidad<br><em>en vivo.</em></h1><p>Un espacio para compartir conocimiento, cultura y los momentos que construyen nuestra comunidad.</p></div>
            <div class="uebtv-login-signal"><span class="uebtv-login-signal__dot"></span><span>Plataforma oficial de transmisiones</span><span class="uebtv-login-signal__line"></span></div>
        </section>
        <section class="uebtv-login-form-panel">
            <div class="uebtv-login-form-wrap">
                <div class="uebtv-login-mobile-logo"><span class="uebtv-login-logo">UEB</span><strong>UEBTV</strong></div>
                <div class="uebtv-login-heading"><span class="uebtv-login-index">01 / ACCESO</span><h2>Bienvenido</h2><p>Ingresa tus credenciales institucionales para continuar.</p></div>
                @if (session('status'))
                    <div class="uebtv-login-alert uebtv-login-alert--success">{{ session('status') }}</div>
                @endif
                <form method="POST" action="{{ route('login') }}" class="uebtv-login-form">
                    @csrf
                    <div class="uebtv-field"><label for="email">Correo electrónico</label><div class="uebtv-input-wrap"><span aria-hidden="true">@</span><input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username" placeholder="nombre@ueb.edu.ec"></div>@error('email')<span class="uebtv-login-error">{{ $message }}</span>@enderror</div>
                    <div class="uebtv-field"><div class="uebtv-label-row"><label for="password">Contraseña</label>@if (Route::has('password.request'))<a href="{{ route('password.request') }}">¿La olvidaste?</a>@endif</div><div class="uebtv-input-wrap"><span aria-hidden="true">••</span><input id="password" type="password" name="password" required autocomplete="current-password" placeholder="Ingresa tu contraseña"></div>@error('password')<span class="uebtv-login-error">{{ $message }}</span>@enderror</div>
                    <label class="uebtv-remember"><input type="checkbox" name="remember" id="remember_me"><span>Recordar mi sesión</span></label>
                    <button type="submit" class="uebtv-login-button">Entrar a UEBTV <span>↗</span></button>
                </form>
                <p class="uebtv-login-footnote">Acceso seguro · Universidad Estatal de Bolívar</p>
            </div>
        </section>
    </main>
</body>
</html>
