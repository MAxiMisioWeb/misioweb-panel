<?php

use App\Http\Controllers\AgencyController;
use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\SessionController;
use App\Http\Controllers\Auth\VerificationController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\ProjectRequestController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', fn (Request $request) => $request->user()
    ? redirect()->route($request->user()->homeRoute())
    : redirect()->route('login'));

Route::middleware('guest')->group(function () {
    Route::get('/ingresar', [SessionController::class, 'create'])->name('login');
    Route::post('/ingresar', [SessionController::class, 'store']);
    Route::get('/registro', [RegisterController::class, 'create'])->name('register');
    Route::post('/registro', [RegisterController::class, 'store'])->middleware('throttle:10,1');

    Route::get('/recuperar', [PasswordResetController::class, 'request'])->name('password.request');
    Route::post('/recuperar', [PasswordResetController::class, 'email'])->middleware('throttle:5,1')->name('password.email');
    Route::get('/restablecer/{token}', [PasswordResetController::class, 'edit'])->name('password.reset');
    Route::post('/restablecer', [PasswordResetController::class, 'update'])->name('password.update');

    Route::get('/auth/google', [GoogleController::class, 'redirect'])->name('google.redirect');
    Route::get('/auth/google/callback', [GoogleController::class, 'callback'])->name('google.callback');
});

Route::middleware('auth')->group(function () {
    Route::post('/salir', [SessionController::class, 'destroy'])->name('logout');

    Route::get('/verificar-email', [VerificationController::class, 'notice'])->name('verification.notice');
    Route::get('/verificar-email/{id}/{hash}', [VerificationController::class, 'verify'])->middleware(['signed', 'throttle:6,1'])->name('verification.verify');
    Route::post('/verificar-email/reenviar', [VerificationController::class, 'send'])->middleware('throttle:6,1')->name('verification.send');

    Route::middleware(['verified', 'role:prospect'])->group(function () {
        Route::get('/pedido', [ProjectRequestController::class, 'create'])->name('request.create');
        Route::post('/pedido', [ProjectRequestController::class, 'store'])->name('request.store');
    });

    Route::middleware(['verified', 'role:client'])->group(function () {
        Route::get('/mi-panel', [ClientController::class, 'index'])->name('client.home');
    });

    Route::middleware(['verified', 'role:agency'])->prefix('agencia')->name('agency.')->group(function () {
        Route::get('/', [AgencyController::class, 'index'])->name('home');
        Route::post('/pedidos/{project}/aprobar', [AgencyController::class, 'approve'])->name('approve');
        Route::post('/pedidos/{project}/rechazar', [AgencyController::class, 'reject'])->name('reject');
    });
});
