<?php

namespace Emitwave\Laravel;

use Emitwave\Client;
use Illuminate\Broadcasting\Broadcasters\Broadcaster;

class EmitwaveBroadcaster extends Broadcaster
{
    public function __construct(private Client $client) {}

    /** @param array<int,string> $channels */
    public function broadcast(array $channels, $event, array $payload = []): void
    {
        foreach ($channels as $channel) {
            // Laravel passes already-prefixed channel strings (private-/presence-),
            // which match Emitwave's channel prefixes — forward verbatim.
            $this->client->trigger((string) $channel, $event, $payload);
        }
    }

    // Emitwave channel authorization is handled by the package's first-party
    // routes, not this sending-only driver.
    public function auth($request)
    {
        throw new \RuntimeException('Emitwave channel auth is handled by the first-party /emitwave/channel-auth endpoint.');
    }

    public function validAuthenticationResponse($request, $result)
    {
        throw new \RuntimeException('Emitwave channel auth is handled by the first-party /emitwave/channel-auth endpoint.');
    }
}
