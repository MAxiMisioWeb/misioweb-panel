@extends('layouts.app')
@section('title', 'Verificá tu email')
@section('heading', 'Verificá tu email')
@section('content')
    <section class="g">
        <p>Te mandamos un enlace a <b>{{ auth()->user()->email }}</b>. Abrilo para confirmar tu cuenta y seguir con tu pedido.</p>
        <p class="muted">¿No llegó? Revisá la carpeta de spam o pedí otro.</p>
        <form method="POST" action="{{ route('verification.send') }}" class="act">
            @csrf
            <button class="btn" type="submit">Mandar de nuevo</button>
        </form>
    </section>
@endsection
