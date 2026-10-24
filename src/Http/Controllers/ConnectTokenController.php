<?php

namespace Emitwave\Laravel\Http\Controllers;

use Emitwave\Client;
use Emitwave\EmitwaveException;
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

        try {
            return response()->json($client->connectionToken((string) $userId));
        } catch (EmitwaveException $exception) {
            return response()->json(
                ['message' => $exception->getMessage()],
                $this->responseStatus($exception),
            );
        }
    }

    private function responseStatus(EmitwaveException $exception): int
    {
        return $exception->status >= 400 && $exception->status <= 599
            ? $exception->status
            : 502;
    }
}
