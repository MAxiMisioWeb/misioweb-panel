<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;
use Throwable;

class PasswordResetController extends Controller
{
    public function request(): View
    {
        return view('auth.forgot-password');
    }

    public function email(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        // Misma respuesta exista o no la cuenta, para no revelar qué emails están registrados.
        try {
            Password::sendResetLink($request->only('email'));
        } catch (Throwable $e) {
            report($e);

            return back()->withErrors(['email' => 'No pudimos mandar el mail. Probá de nuevo en un rato o escribinos a contacto@misioweb.com.']);
        }

        return back()->with('status', 'Si hay una cuenta con ese email, te mandamos un enlace para elegir una contraseña nueva.');
    }

    public function edit(Request $request, string $token): View
    {
        return view('auth.reset-password', ['token' => $token, 'email' => $request->query('email')]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)]);
                $user->email_verified_at ??= now(); // si llegó el mail, el email es suyo
                $user->save();
                event(new PasswordReset($user));
            }
        );

        return $status === Password::PASSWORD_RESET
            ? redirect()->route('login')->with('status', 'Listo, ya podés ingresar con tu contraseña nueva.')
            : back()->withInput($request->only('email'))->withErrors(['email' => 'El enlace venció o no es válido. Pedí uno nuevo.']);
    }
}
