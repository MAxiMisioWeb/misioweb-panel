<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Site;
use App\Services\SiteSync;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * POST /api/v1/sync: lo llama el plugin MisioWeb Progreso de cada sitio, con su token
 * (Authorization: Bearer). Solo recibe señales sí/no y conteos.
 */
class SyncController extends Controller
{
    public function __invoke(Request $request, SiteSync $sync): JsonResponse
    {
        $token = (string) $request->bearerToken();
        $site = $token !== '' ? Site::findByToken($token) : null;
        if (! $site) {
            return response()->json(['message' => 'Token inválido.'], 401);
        }

        $data = $request->validate([
            'site_id' => ['required'],
            'site' => ['nullable', 'string', 'max:255'],
            'plugin' => ['nullable', 'string', 'max:20'],
            'wp' => ['nullable', 'string', 'max:20'],
            'signals' => ['present', 'array', 'max:50'],
            'signals.*' => ['array:ok,detail'],
            'signals.*.ok' => ['required', 'boolean'],
            'signals.*.detail' => ['nullable', 'string', 'max:255'],
        ]);

        if ((string) $data['site_id'] !== (string) $site->id) {
            return response()->json(['message' => 'El ID del sitio no coincide con el token.'], 403);
        }
        foreach (array_keys($data['signals']) as $key) {
            if (! preg_match('/^[a-z0-9_]{1,32}$/', (string) $key)) {
                return response()->json(['message' => "Señal inválida: {$key}"], 422);
            }
        }

        return response()->json($sync->sync($site, $data['signals']));
    }
}
