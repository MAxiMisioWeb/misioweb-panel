<?php

use App\Http\Controllers\Api\SyncController;
use Illuminate\Support\Facades\Route;

// Plugin MisioWeb Progreso. En el plugin, "URL del panel" = https://panel.misioweb.com/api
Route::post('/v1/sync', SyncController::class)->middleware('throttle:30,1')->name('api.sync');
