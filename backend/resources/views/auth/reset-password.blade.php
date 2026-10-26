@extends('layouts.app')
@section('title', 'Contraseña nueva')
@section('content')
    <h1>Contraseña nueva</h1>
    <form method="POST" action="{{ route('password.update') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <label class="fl"><span>Email</span>
            <input type="email" name="email" value="{{ old('email', $email) }}" required autocomplete="email">
        </label>
        @error('email')<div class="err">{{ $message }}</div>@enderror
        <label class="fl"><span>Contraseña nueva (mínimo 8 caracteres)</span>
            <input type="password" name="password" required autofocus autocomplete="new-password">
        </label>
        @error('password')<div class="err">{{ $message }}</div>@enderror
        <label class="fl"><span>Repetí la contraseña</span>
            <input type="password" name="password_confirmation" required autocomplete="new-password">
        </label>
        <div class="act"><button class="btn block" type="submit">Guardar contraseña</button></div>
    </form>
@endsection
