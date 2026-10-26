@extends('layouts.app')
@section('title', 'Crear cuenta')
@section('content')
    <h1>Crear cuenta</h1>
    <p class="muted">Creá tu cuenta para elegir los widgets de tu sitio y mandarnos tu pedido.</p>

    @include('auth.google-button', ['label' => 'Crear cuenta con Google', 'style' => 'margin-top:16px'])

    <div class="or">o con tu email</div>

    <form method="POST" action="{{ route('register') }}">
        @csrf
        <label class="fl"><span>Tu nombre</span>
            <input type="text" name="name" value="{{ old('name') }}" required autocomplete="name">
        </label>
        @error('name')<div class="err">{{ $message }}</div>@enderror
        <label class="fl"><span>Email</span>
            <input type="email" name="email" value="{{ old('email') }}" required autocomplete="email">
        </label>
        @error('email')<div class="err">{{ $message }}</div>@enderror
        <label class="fl"><span>Contraseña (mínimo 8 caracteres)</span>
            <input type="password" name="password" required autocomplete="new-password">
        </label>
        @error('password')<div class="err">{{ $message }}</div>@enderror
        <label class="fl"><span>Repetí la contraseña</span>
            <input type="password" name="password_confirmation" required autocomplete="new-password">
        </label>
        <div class="act"><button class="btn block" type="submit">Crear cuenta</button></div>
    </form>

    <p class="foot">¿Ya tenés cuenta? <a href="{{ route('login') }}">Ingresá</a></p>
@endsection
