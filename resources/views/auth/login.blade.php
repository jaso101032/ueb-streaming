<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#071a35">
    <title>Acceso a UEBTV</title>
    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/app.js'], 'uebtv-build-v2')
</head>
<body class="access-page">
    <a class="access-skip" href="#email">Ir al inicio de sesión</a>
    <main class="access-layout">
        <section class="access-brand" aria-labelledby="access-brand-title">
            <div class="access-orbit" aria-hidden="true"></div>
            <a class="access-logo" href="{{ route('eventos.index') }}" aria-label="UEBTV, ir al catálogo">
                <img class="brand-mascot brand-mascot-access" src="{{ asset('images/mascota-ueb-login.png') }}" alt="Mascota de la UEB" width="64" height="88">
                <span class="access-logo__text">UEB<span>TV</span><small>Universidad Estatal de Bolívar</small></span>
            </a>
            <div class="access-story">
                <p class="access-eyebrow"><span></span> TU UNIVERSIDAD. TU SEÑAL.</p>
                <h1 id="access-brand-title">La Universidad<br><em>en vivo.</em></h1>
                <p class="access-story__copy">Las ideas nos conectan.<br>Los momentos nos hacen comunidad.</p>
                <a class="access-explore" href="{{ route('eventos.index') }}">
                    <span class="access-play" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><path d="m9 6 9 6-9 6V6Z" fill="currentColor"/></svg></span>
                    <span>Descubre nuestra programación<small>Explora UEBTV sin iniciar sesión</small></span>
                    <span class="access-arrow" aria-hidden="true">↗</span>
                </a>
            </div>
            <div class="access-brand-footer">
                <span class="access-signal"><i aria-hidden="true"></i> Plataforma institucional</span>
                <span class="access-brand-footer__place">Guaranda, Ecuador</span>
            </div>
        </section>
        <section class="access-entry" aria-labelledby="access-form-title">
            <nav class="access-entry-nav" aria-label="Navegación de acceso"><a href="{{ route('eventos.index') }}"><span aria-hidden="true">←</span> Volver al catálogo</a><span>COMUNIDAD UEB</span></nav>
            <div class="access-card">
                <div class="access-card-top"><span class="access-card-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="5" width="18" height="13" rx="3"/><path d="m10 9 5 3-5 3V9ZM9 21h6"/></svg></span><span>UN ESPACIO PARA CONECTAR</span></div>
                <header class="access-heading"><h2 id="access-form-title">Bienvenido<br>de nuevo<span>.</span></h2><p>Tu comunidad está aquí.<br>Ingresa a tu cuenta y sé parte de ella.</p></header>
                @if (session('status'))<div class="access-notice" role="status">{{ session('status') }}</div>@endif
                <form method="POST" action="{{ route('login') }}" class="access-form" x-data="{ showPassword: false, capsLock: false }">
                    @csrf
                    <div class="access-field">
                        <label for="email">Correo electrónico</label>
                        <div class="access-input @error('email') access-input--error @enderror">
                            <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="5" width="18" height="14" rx="3"/><path d="m4 7 8 6 8-6"/></svg>
                            <input id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="username" inputmode="email" autocapitalize="none" spellcheck="false" placeholder="nombre@ueb.edu.ec" @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
                        </div>
                        @error('email')<p id="email-error" class="access-error" role="alert">{{ $message }}</p>@enderror
                    </div>
                    <div class="access-field">
                        <div class="access-field-label"><label for="password">Contraseña</label>@if (Route::has('password.request'))<a href="{{ route('password.request') }}">¿La olvidaste?</a>@endif</div>
                        <div class="access-input @error('password') access-input--error @enderror">
                            <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="4" y="10" width="16" height="11" rx="3"/><path d="M8 10V7a4 4 0 0 1 8 0v3m-4 4v3"/></svg>
                            <input id="password" type="password" :type="showPassword ? 'text' : 'password'" name="password" required autocomplete="current-password" placeholder="Ingresa tu contraseña" aria-describedby="caps-lock @error('password') password-error @enderror" @error('password') aria-invalid="true" @enderror @keyup="capsLock = $event.getModifierState('CapsLock')" @keydown="capsLock = $event.getModifierState('CapsLock')" @blur="capsLock = false">
                            <button class="access-reveal" type="button" x-cloak @click="showPassword = !showPassword" :aria-label="showPassword ? 'Ocultar contraseña' : 'Mostrar contraseña'" :aria-pressed="showPassword" aria-controls="password">
                                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/><path x-show="showPassword" d="m3 3 18 18"/></svg>
                            </button>
                        </div>
                        <p id="caps-lock" class="access-caps" x-cloak x-show="capsLock" role="status">Tienes activado Bloq Mayús.</p>
                        @error('password')<p id="password-error" class="access-error" role="alert">{{ $message }}</p>@enderror
                    </div>
                    <label class="access-remember"><input type="checkbox" name="remember" id="remember_me" @checked(old('remember'))><span>Mantener mi sesión iniciada</span></label>
                    <button type="submit" class="access-submit"><span>Entrar a UEBTV</span><svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 12h15m-6-6 6 6-6 6"/></svg></button>
                </form>
                @if (Route::has('register'))<div class="access-register"><span>¿Primera vez aquí?</span><a href="{{ route('register') }}">Crea tu cuenta <span aria-hidden="true">↗</span></a></div>@endif
            </div>
            <footer class="access-entry-footer"><span>Universidad Estatal de Bolívar</span><span>Conocimiento que nos une.</span></footer>
        </section>
    </main>
</body>
</html>
