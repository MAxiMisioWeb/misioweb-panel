<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class VerificationController extends Controller
{
    public function notice(Request $request): View|RedirectResponse
    {
        return $request->user()->hasVerifiedEmail()
            ? redirect()->route($request->user()->homeRoute())
            : view('auth.verify-email');
    }

    public function verify(EmailVerificationRequest $request): RedirectResponse
    {
        $request->fulfill();

        return redirect()->route($request->user()->homeRoute())->with('status', 'Email verificado.');
    }

    public function send(Request $request): RedirectResponse
    {
        try {
            $request->user()->sendEmailVerificationNotification();
        } catch (Throwable $e) {
            report($e);

            return back()->withErrors(['review' => 'No pudimos mandar el mail. Probá de nuevo en un rato o escribinos a contacto@misioweb.com.']);
        }

        return back()->with('status', 'Te mandamos un enlace nuevo.');
    }
}
