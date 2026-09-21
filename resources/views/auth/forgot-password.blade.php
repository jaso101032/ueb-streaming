<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Recuperar acceso · UEBTV</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'], 'uebtv-build-v2')
</head>
<body class="uebtv-login-body">
    <main class="uebtv-login-shell uebtv-recovery-shell">
        <section class="uebtv-login-brand">
            <div class="uebtv-login-brand__top"><span class="uebtv-login-logo">UEB</span><span class="uebtv-login-brand__name">UEBTV</span></div>
            <div class="uebtv-login-brand__message"><p class="uebtv-kicker"><span></span> Centro de acceso</p><h1>Vuelve a<br><em>estar conectado.</em></h1><p>Recupera tu acceso a la plataforma institucional de transmisiones UEBTV.</p></div>
            <div class="uebtv-login-signal"><span class="uebtv-login-signal__dot"></span><span>Plataforma oficial de transmisiones</span><span class="uebtv-login-signal__line"></span></div>
        </section>
        <section class="uebtv-login-form-panel">
            <div class="uebtv-login-form-wrap">
                <div class="uebtv-recovery-back"><a href="{{ route('login') }}"><span>←</span> Volver al acceso</a></div>
                <div class="uebtv-login-heading"><span class="uebtv-login-index">02 / RECUPERACIÓN</span><h2>Restablecer acceso</h2><p>Te enviaremos un enlace para crear una nueva contraseña.</p></div>
                <div class="uebtv-login-divider"><span>Correo institucional</span><i></i></div>
                <x-auth-session-status class="uebtv-recovery-status" :status="session('status')" />
                <form method="POST" action="{{ route('password.email') }}" class="uebtv-login-form">
                    @csrf
                    <div class="uebtv-field"><label for="email">Correo electrónico</label><div class="uebtv-input-wrap"><span aria-hidden="true">@</span><input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username" placeholder="nombre@ueb.edu.ec"></div>@error('email')<span class="uebtv-login-error">{{ $message }}</span>@enderror</div>
                    <button type="submit" class="uebtv-login-button">Enviar enlace de recuperación <span>↗</span></button>
                </form>
                <p class="uebtv-login-footnote">Universidad Estatal de Bolívar <span>·</span> UEBTV</p>
            </div>
        </section>
    </main>
</body>
</html>
