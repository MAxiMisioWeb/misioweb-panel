@extends('layouts.app')
@section('title', 'Ingresar')
@section('content')
    <h1>Ingresar</h1>

    @include('auth.google-button', ['label' => 'Ingresar con Google'])

    <div class="or">o con tu email</div>

    <form method="POST" action="{{ route('login') }}">
        @csrf
        <label class="fl"><span>Email</span>
            <input type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email">
        </label>
        @error('email')<div class="err">{{ $message }}</div>@enderror
        <label class="fl"><span>Contraseña</span>
            <input type="password" name="password" required autocomplete="current-password">
        </label>
        <label class="ck"><input type="checkbox" name="remember" value="1"><div>Recordarme en este dispositivo</div></label>
        <div class="act"><button class="btn block" type="submit">Ingresar</button></div>
    </form>

    <p class="foot"><a href="{{ route('password.request') }}">Olvidé mi contraseña</a> · <a href="{{ route('register') }}">Crear cuenta</a></p>
@endsection
