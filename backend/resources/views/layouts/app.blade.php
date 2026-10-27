<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>@yield('title', 'Panel') · MisioWeb</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;600&family=Varela+Round&display=swap" rel="stylesheet">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="{{ asset('css/panel.css') }}">
    @stack('head')
    <script src="{{ asset('js/panel.js') }}" defer></script>
</head>
<body>
@auth
    <div class="wrap">
        <nav class="top">
            <div>
                <div class="brand">MisioWeb · Panel de proyectos</div>
                <h1>@yield('heading')</h1>
            </div>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <span class="who">{{ auth()->user()->email }}</span>
                <button class="btn ghost" type="submit">Salir</button>
            </form>
        </nav>
        <div style="margin-top:16px">
            @include('layouts.messages')
            @yield('content')
        </div>
    </div>
@else
    <div class="auth">
        <div class="brand">MisioWeb · Panel de proyectos</div>
        @include('layouts.messages')
        @yield('content')
    </div>
@endauth
</body>
</html>
