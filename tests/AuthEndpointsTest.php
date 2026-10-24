<?php

namespace Emitwave\Laravel\Tests;

use Emitwave\Client;
use Emitwave\EmitwaveException;
use Emitwave\Laravel\Facades\Emitwave;
use Illuminate\Http\Request;

class AuthEndpointsTest extends TestCase
{
    public function test_authenticated_host_user_receives_connection_token(): void
    {
        $client = $this->createMock(Client::class);
        $client->expects($this->once())->method('connectionToken')->with('42', [], 'https://toladot.com')->willReturn([
            'token' => 'connection.jwt', 'ttl' => 900, 'app_id' => 'app_1',
        ]);
        $this->app->instance(Client::class, $client);

        $this->withHeaders(['X-Test-User' => '42', 'Origin' => 'https://toladot.com'])->postJson('/emitwave/connect-token')
            ->assertOk()
            ->assertExactJson(['token' => 'connection.jwt', 'ttl' => 900, 'app_id' => 'app_1']);
    }

    public function test_unauthenticated_request_is_rejected_before_token_minting(): void
    {
        $client = $this->createMock(Client::class);
        $client->expects($this->never())->method('connectionToken');
        $this->app->instance(Client::class, $client);

        $this->postJson('/emitwave/connect-token')->assertUnauthorized();
    }

    public function test_private_channel_callback_authorizes_owner_and_mints_token(): void
    {
        Emitwave::channel('private-user.{id}', fn ($user, string $id): bool => (string) $user->id === $id);
        $client = $this->createMock(Client::class);
        $client->expects($this->once())->method('subscriptionToken')
            ->with('42', 'private-user.42', [])
            ->willReturn(['token' => 'subscription.jwt', 'ttl' => 300, 'channel' => 'private:app_1.user.42']);
        $this->app->instance(Client::class, $client);

        $this->withHeader('X-Test-User', '42')
            ->postJson('/emitwave/channel-auth', ['channel' => 'private-user.42'])
            ->assertOk()
            ->assertJsonPath('subscription_token', 'subscription.jwt');
    }

    public function test_denied_private_channel_never_mints_token(): void
    {
        Emitwave::channel('private-user.{id}', fn ($user, string $id): bool => (string) $user->id === $id);
        $client = $this->createMock(Client::class);
        $client->expects($this->never())->method('subscriptionToken');
        $this->app->instance(Client::class, $client);

        $this->withHeader('X-Test-User', '42')
            ->postJson('/emitwave/channel-auth', ['channel' => 'private-user.7'])
            ->assertForbidden();
    }

    public function test_encrypted_channel_returns_gateway_derived_shared_secret(): void
    {
        Emitwave::channel('private-encrypted-user.{id}', fn ($user, string $id): bool => (string) $user->id === $id);
        $client = $this->createMock(Client::class);
        $client->expects($this->once())->method('subscriptionToken')
            ->with('42', 'private-encrypted-user.42', [])
            ->willReturn([
                'token' => 'subscription.jwt',
                'ttl' => 300,
                'channel' => 'encrypted:app_1.user.42',
                'shared_secret' => 'base64-channel-key',
            ]);
        $this->app->instance(Client::class, $client);

        $this->withHeader('X-Test-User', '42')
            ->postJson('/emitwave/channel-auth', ['channel' => 'private-encrypted-user.42'])
            ->assertOk()
            ->assertJsonPath('shared_secret', 'base64-channel-key');
    }

    public function test_presence_callback_controls_identity_and_info(): void
    {
        Emitwave::channel('presence-room.{room}', fn ($user, string $room): array => [
            'id' => (string) $user->id,
            'info' => ['room' => $room],
        ]);
        $client = $this->createMock(Client::class);
        $client->expects($this->once())->method('subscriptionToken')
            ->with('42', 'presence-room.lobby', ['room' => 'lobby'])
            ->willReturn(['token' => 'presence.jwt', 'ttl' => 300, 'channel' => 'presence:app_1.room.lobby']);
        $this->app->instance(Client::class, $client);

        $this->withHeader('X-Test-User', '42')
            ->postJson('/emitwave/channel-auth', ['channel' => 'presence-room.lobby'])
            ->assertOk()
            ->assertJsonPath('presence.id', '42')
            ->assertJsonPath('presence.info.room', 'lobby');
    }

    public function test_channel_auth_validates_channel_and_rejects_public_channels(): void
    {
        $this->withHeader('X-Test-User', '42')->postJson('/emitwave/channel-auth', [])
            ->assertUnprocessable();
        $this->withHeader('X-Test-User', '42')->postJson('/emitwave/channel-auth', ['channel' => 'news'])
            ->assertUnprocessable();
    }

    public function test_gateway_errors_return_only_a_safe_message(): void
    {
        Emitwave::channel('private-user.{id}', fn ($user, string $id): bool => (string) $user->id === $id);
        $client = $this->createMock(Client::class);
        $client->method('connectionToken')->willThrowException(
            new EmitwaveException(422, '{"error":{"code":"INVALID_TOKEN_REQUEST"}}'),
        );
        $client->method('subscriptionToken')->willThrowException(
            new EmitwaveException(503, '{"error":{"code":"TOKEN_MINT_FAILED"}}'),
        );
        $this->app->instance(Client::class, $client);

        $this->withHeader('X-Test-User', '42')
            ->postJson('/emitwave/connect-token')
            ->assertStatus(422)
            ->assertExactJson(['message' => 'Emitwave publish failed (HTTP 422)']);

        $this->withHeader('X-Test-User', '42')
            ->postJson('/emitwave/channel-auth', ['channel' => 'private-user.42'])
            ->assertStatus(503)
            ->assertExactJson(['message' => 'Emitwave publish failed (HTTP 503)']);
    }
}

class TestUserMiddleware
{
    public function handle(Request $request, \Closure $next)
    {
        if ($id = $request->header('X-Test-User')) {
            $request->setUserResolver(fn () => (object) ['id' => (int) $id]);
        }

        return $next($request);
    }
}
