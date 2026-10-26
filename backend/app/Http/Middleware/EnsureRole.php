<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Deja pasar solo a los roles indicados; al resto lo manda a su pantalla de inicio.
 * Uso en rutas: ->middleware('role:agency') o 'role:prospect,client'.
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || ! in_array($user->role, $roles, true)) {
            return $user ? redirect()->route($user->homeRoute()) : redirect()->guest(route('login'));
        }

        return $next($request);
    }
}
