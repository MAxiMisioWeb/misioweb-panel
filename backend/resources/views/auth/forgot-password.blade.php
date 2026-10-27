@extends('layouts.app')
@section('title', 'Recuperar contraseña')
@section('content')
    <h1>Recuperar contraseña</h1>
    <p class="muted">Te mandamos un enlace para elegir una contraseña nueva. Si entrás con Google, no hace falta.</p>
    <form method="POST" action="{{ route('password.email') }}">
        @csrf
        <label class="fl"><span>Email</span>
            <input type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email">
        </label>
        @error('email')<div class="err">{{ $message }}</div>@enderror
        <div class="act"><button class="btn block" type="submit">Mandar enlace</button></div>
    </form>
    <p class="foot"><a href="{{ route('login') }}">Volver a ingresar</a></p>
@endsection
