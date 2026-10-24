<?php

namespace Emitwave\Laravel\Http\Controllers;

use Emitwave\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConnectTokenController
{
    public function __invoke(Request $request, Client $client): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $userId = method_exists($user, 'getAuthIdentifier') ? $user->getAuthIdentifier() : $user->id;

        return response()->json($client->connectionToken((string) $userId));
    }
}
