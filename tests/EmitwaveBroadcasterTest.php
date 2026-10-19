<?php

namespace Emitwave\Laravel\Tests;

use Emitwave\Client;
use Emitwave\Laravel\EmitwaveBroadcaster;

class EmitwaveBroadcasterTest extends TestCase
{
    public function test_broadcast_triggers_once_per_channel(): void
    {
        $calls = [];
        $client = $this->createMock(Client::class);
        $client->method('trigger')->willReturnCallback(function ($channel, $event, $data) use (&$calls) {
            $calls[] = [$channel, $event, $data];
        });
        $b = new EmitwaveBroadcaster($client);
        $b->broadcast(['orders', 'private-user.1'], 'PaymentReceived', ['amount' => 42]);

        $this->assertSame([
            ['orders', 'PaymentReceived', ['amount' => 42]],
            ['private-user.1', 'PaymentReceived', ['amount' => 42]],
        ], $calls);
    }

    public function test_service_provider_registers_emitwave_driver(): void
    {
        config()->set('broadcasting.connections.emitwave', [
            'driver' => 'emitwave', 'gateway_url' => 'http://gw:8080',
            'app_id' => 'app_1', 'key' => 'ew_pk_test_x', 'secret' => 'sekret',
        ]);
        $mgr = $this->app->make(\Illuminate\Broadcasting\BroadcastManager::class);
        $driver = $mgr->connection('emitwave');
        $this->assertInstanceOf(EmitwaveBroadcaster::class, $driver);
    }

    public function test_missing_config_key_throws_clearly(): void
    {
        config()->set('broadcasting.connections.emitwave', [
            'driver' => 'emitwave', 'gateway_url' => 'http://gw:8080',
            'app_id' => 'app_1', 'key' => 'ew_pk_test_x', // no 'secret'
        ]);
        $mgr = $this->app->make(\Illuminate\Broadcasting\BroadcastManager::class);
        $this->expectException(\InvalidArgumentException::class);
        $mgr->connection('emitwave');
    }
}
