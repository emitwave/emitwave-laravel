<?php

namespace Emitwave\Laravel\Http\Controllers;

use Emitwave\Client;
use Emitwave\EmitwaveException;
use Emitwave\Laravel\ChannelRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChannelAuthController
{
    public function __invoke(Request $request, ChannelRegistry $channels, Client $client): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }
        $data = $request->validate(['channel' => ['required', 'string', 'max:200']]);
        $channel = $data['channel'];
        $presence = str_starts_with($channel, 'presence-') || str_starts_with($channel, 'presence-cache-');
        $private = str_starts_with($channel, 'private-');
        if (! $private && ! $presence) {
            return response()->json(['message' => 'Public channels do not require authorization.'], 422);
        }

        $result = $channels->resolve($channel, $user);
        if ($presence) {
            if (! is_array($result) || ! isset($result['id'])) {
                return response()->json(['message' => 'Forbidden.'], 403);
            }
            $id = (string) $result['id'];
            $info = is_array($result['info'] ?? null) ? $result['info'] : [];
            try {
                $minted = $client->subscriptionToken($id, $channel, $info);
            } catch (EmitwaveException $exception) {
                return $this->errorResponse($exception);
            }

            return response()->json([
                'subscription_token' => $minted['token'],
                'ttl' => $minted['ttl'],
                'channel' => $minted['channel'],
                'presence' => ['id' => $id, 'info' => $info],
            ]);
        }
        if (! $result) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $userId = method_exists($user, 'getAuthIdentifier') ? $user->getAuthIdentifier() : $user->id;
        try {
            $minted = $client->subscriptionToken((string) $userId, $channel);
        } catch (EmitwaveException $exception) {
            return $this->errorResponse($exception);
        }

        $response = [
            'subscription_token' => $minted['token'],
            'ttl' => $minted['ttl'],
            'channel' => $minted['channel'],
        ];
        if (isset($minted['shared_secret'])) {
            $response['shared_secret'] = $minted['shared_secret'];
        }

        return response()->json($response);
    }

    private function errorResponse(EmitwaveException $exception): JsonResponse
    {
        $status = $exception->status >= 400 && $exception->status <= 599
            ? $exception->status
            : 502;

        return response()->json(['message' => $exception->getMessage()], $status);
    }
}
