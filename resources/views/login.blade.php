<!doctype html>
<html lang="es">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Iniciar sesión — Iron Screw</title>@vite(['resources/css/app.css'])</head>
<body class="login-page">
<form class="login-card" method="post" action="{{ route('login.submit') }}">
    @csrf
    <img class="login-logo" src="{{ asset('assets/figma/logo-iron.png') }}" alt="Iron Screw">
    <h1>Sistema de Etiquetas</h1>
    <label>Usuario</label>
    <div class="login-field"><img src="{{ asset('assets/figma/user.svg') }}" alt=""><input name="usuario" placeholder="Ingrese su usuario" autocomplete="username"></div>
    <label>Contraseña</label>
    <div class="login-field lock"><span><img src="{{ asset('assets/figma/lock-body.svg') }}" alt=""><img src="{{ asset('assets/figma/lock-top.svg') }}" alt=""></span><input type="password" name="password" placeholder="Ingrese su contraseña" autocomplete="current-password"></div>
    <button>Iniciar Sesión</button>
</form>
</body></html>
