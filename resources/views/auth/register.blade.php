<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Crear cuenta · UEBTV</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'], 'uebtv-build-v2')
    <style>
        .uebtv-password-requirements { margin: 10px 0 0; padding-left: 20px; list-style: decimal; color: #475569; font-size: 12px; line-height: 1.8; }
        .uebtv-password-dot { display: inline-block; width: 8px; height: 8px; margin-right: 6px; border-radius: 50%; background: #dc2626; transition: background-color .15s; }
        .uebtv-password-requirements li[data-met="true"] .uebtv-password-dot { background: #15803d; }
    </style>
</head>
<body class="uebtv-login-body">
    <main class="uebtv-login-shell uebtv-register-shell">
        <section class="uebtv-login-brand">
            <figure class="access-mascot">
                <img src="{{ asset('images/mascota-ueb-login.png') }}" alt="Mascota de la UEB con su vestimenta institucional" width="1024" height="1536" fetchpriority="high">
            </figure>
            <div class="uebtv-login-brand__top"><span class="uebtv-login-logo">UEB</span><span class="uebtv-login-brand__name">UEBTV</span></div>
            <div class="uebtv-login-brand__message"><p class="uebtv-kicker"><span></span> Comunidad UEBTV</p><h1>Forma parte<br><em>de la señal.</em></h1><p>Crea tu cuenta para descubrir transmisiones, eventos y contenidos de la Universidad Estatal de Bolívar.</p></div>
            <div class="uebtv-login-signal"><span class="uebtv-login-signal__dot"></span><span>Plataforma oficial de transmisiones</span><span class="uebtv-login-signal__line"></span></div>
        </section>
        <section class="uebtv-login-form-panel">
            <div class="uebtv-login-form-wrap">
                <div class="uebtv-recovery-back"><a href="{{ route('login') }}"><span>←</span> Volver al acceso</a></div>
                <div class="uebtv-login-heading"><span class="uebtv-login-index">02 / REGISTRO</span><h2>Crear cuenta</h2><p>Regístrate para acceder a la programación de UEBTV.</p></div>
                <div class="uebtv-login-divider"><span>Datos personales</span><i></i></div>
                <form method="POST" action="{{ route('register') }}" class="uebtv-login-form">
                    @csrf
                    <div class="uebtv-field"><label for="name">Nombre completo</label><div class="uebtv-input-wrap"><span aria-hidden="true">✦</span><input id="name" type="text" name="name" value="{{ old('name') }}" required autofocus autocomplete="name" placeholder="Tu nombre completo"></div>@error('name')<span class="uebtv-login-error">{{ $message }}</span>@enderror</div>
                    <div class="uebtv-field"><label for="email">Correo electrónico</label><div class="uebtv-input-wrap"><span aria-hidden="true">@</span><input id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="username" placeholder="nombre@ueb.edu.ec"></div>@error('email')<span class="uebtv-login-error">{{ $message }}</span>@enderror</div>
                    <div class="uebtv-field"><label for="password">Contraseña</label><div class="uebtv-input-wrap"><span aria-hidden="true">••</span><input id="password" type="password" name="password" required minlength="8" aria-describedby="password-requirements" autocomplete="new-password" placeholder="Crea una contraseña"></div>
                        <ol id="password-requirements" class="uebtv-password-requirements">
                            @foreach (['length' => 'Mínimo 8 caracteres', 'uppercase' => 'Al menos una letra mayúscula', 'lowercase' => 'Al menos una letra minúscula', 'number' => 'Al menos un número', 'symbol' => 'Al menos un símbolo (por ejemplo: !, @, #)'] as $rule => $description)
                                <li data-password-rule="{{ $rule }}" data-met="false"><span class="uebtv-password-dot" aria-hidden="true"></span>{{ $description }}<span class="sr-only" data-requirement-status> — Pendiente</span></li>
                            @endforeach
                        </ol>
                    @error('password')<span class="uebtv-login-error">{{ $message }}</span>@enderror</div>
                    <div class="uebtv-field"><label for="password_confirmation">Confirmar contraseña</label><div class="uebtv-input-wrap"><span aria-hidden="true">••</span><input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password" placeholder="Repite tu contraseña"></div>@error('password_confirmation')<span class="uebtv-login-error">{{ $message }}</span>@enderror</div>
                    <button type="submit" class="uebtv-login-button">Crear mi cuenta <span>↗</span></button>
                </form>
                <p class="uebtv-login-footnote">Universidad Estatal de Bolívar <span>·</span> UEBTV</p>
            </div>
        </section>
    </main>
    <script>
        const passwordInput = document.getElementById('password');
        const passwordRules = {
            length: (value) => Array.from(value).length >= 8,
            uppercase: (value) => /\p{Lu}/u.test(value),
            lowercase: (value) => /\p{Ll}/u.test(value),
            number: (value) => /\p{N}/u.test(value),
            symbol: (value) => /[\p{S}\p{P}]/u.test(value),
        };
        const updatePasswordRequirements = () => {
            const value = passwordInput.value;
            document.querySelectorAll('[data-password-rule]').forEach((item) => {
                const met = passwordRules[item.dataset.passwordRule](value);
                item.dataset.met = String(met);
                item.querySelector('[data-requirement-status]').textContent = met ? ' — Cumplido' : ' — Pendiente';
            });
        };
        passwordInput.addEventListener('input', updatePasswordRequirements);
        passwordInput.addEventListener('change', updatePasswordRequirements);
        window.addEventListener('pageshow', updatePasswordRequirements);
        updatePasswordRequirements();
    </script>
</body>
</html>
