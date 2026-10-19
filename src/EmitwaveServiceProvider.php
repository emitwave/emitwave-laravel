<?php

namespace Emitwave\Laravel;

use Emitwave\Client;
use Emitwave\CurlHttpClient;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\ServiceProvider;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Client\ClientInterface;

class EmitwaveServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Broadcast::extend('emitwave', function ($app, array $config) {
            foreach (['gateway_url', 'app_id', 'key', 'secret'] as $required) {
                if (empty($config[$required])) {
                    throw new \InvalidArgumentException("Emitwave broadcasting connection is missing required config: {$required}");
                }
            }
            $f = new Psr17Factory();
            $http = $app->bound(ClientInterface::class) ? $app->make(ClientInterface::class) : new CurlHttpClient($f);

            return new EmitwaveBroadcaster(new Client(
                $config['gateway_url'], $config['app_id'], $config['key'], $config['secret'], $http, $f, $f,
            ));
        });
    }
}
