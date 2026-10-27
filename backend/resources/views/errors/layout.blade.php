<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') · MisioWeb</title>
    <link rel="stylesheet" href="{{ asset('css/panel.css') }}">
</head>
<body>
    <div class="auth">
        <div class="brand">MisioWeb · Panel de proyectos</div>
        <h1>@yield('title')</h1>
        <p>@yield('message')</p>
        <div class="act"><a class="btn" href="{{ url('/') }}">Volver al inicio</a></div>
    </div>
</body>
</html>
