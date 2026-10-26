<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

/**
 * Ingreso con Google. Si el email ya tiene cuenta (por ejemplo, una de la agencia), se vincula;
 * si no, se crea un prospecto. Solo se aceptan emails que Google marca como verificados.
 */
class GoogleController extends Controller
{
    public function redirect(): RedirectResponse
    {
        return Socialite::driver('google')->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        try {
            $google = Socialite::driver('google')->user();
        } catch (Throwable) {
            return redirect()->route('login')->withErrors(['email' => 'No se pudo ingresar con Google. Probá de nuevo.']);
        }

        $email = strtolower((string) $google->getEmail());
        if ($email === '' || ! ($google->getRaw()['email_verified'] ?? false)) {
            return redirect()->route('login')->withErrors(['email' => 'Tu cuenta de Google no tiene un email verificado.']);
        }

        $user = User::where('google_id', $google->getId())->first()
            ?? User::where('email', $email)->first()
            ?? new User(['name' => $google->getName() ?: $email, 'email' => $email]);

        if (! $user->exists) {
            $user->role = User::ROLE_PROSPECT;
        }
        $user->google_id = $google->getId();
        $user->email_verified_at ??= now();
        $user->save();

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return redirect()->intended(route($user->homeRoute()));
    }
}
