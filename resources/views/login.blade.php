<!doctype html>
<html lang="es">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Iniciar sesión — Iron Screw</title>@vite(['resources/css/app.css','resources/js/app.js'])</head>
<body class="login-page">
<form class="login-card" method="post" action="{{ route('login.submit') }}">
    @csrf
    <img class="login-logo" src="{{ asset('assets/figma/logo-iron.png') }}" alt="Iron Screw">
    <h1>Sistema de Etiquetas</h1>
    <label>Usuario</label>
    <div class="login-field"><img src="{{ asset('assets/figma/user.svg') }}" alt=""><input name="usuario" placeholder="Ingrese su usuario" autocomplete="username"></div>
    <label>Contraseña</label>
    <div class="login-field lock"><span><img src="{{ asset('assets/figma/lock-body.svg') }}" alt=""><img src="{{ asset('assets/figma/lock-top.svg') }}" alt=""></span><input id="login-password" type="password" name="password" placeholder="Ingrese su contraseña" autocomplete="current-password"><button class="password-toggle" type="button" data-password-toggle="login-password" aria-label="Mostrar contraseña" aria-pressed="false" title="Mostrar contraseña"><svg class="eye-show" viewBox="0 0 24 24" aria-hidden="true"><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="2.5"/></svg><svg class="eye-hide" viewBox="0 0 24 24" aria-hidden="true"><path d="m3 3 18 18M10.6 6.2A9.7 9.7 0 0 1 12 6c6 0 9.5 6 9.5 6a17 17 0 0 1-2.2 2.9M6.2 6.2C3.8 8 2.5 12 2.5 12s3.5 6 9.5 6a9.7 9.7 0 0 0 3.4-.6M9.9 9.9a3 3 0 0 0 4.2 4.2"/></svg></button></div>
    <button>Iniciar Sesión</button>
</form>
</body></html>
