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
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/emitwave.php', 'emitwave');
        $this->app->singleton(ChannelRegistry::class);
        $this->app->singleton(Emitwave::class);
        $this->app->singleton(Client::class, function ($app): Client {
            $factory = new Psr17Factory;
            $http = $app->bound(ClientInterface::class) ? $app->make(ClientInterface::class) : new CurlHttpClient($factory);

            return new Client(
                appId: (string) config('emitwave.app_id'),
                publicKey: (string) config('emitwave.key'),
                secret: (string) config('emitwave.secret'),
                gatewayUrl: (string) config('emitwave.gateway_url'),
                http: $http,
                requestFactory: $factory,
                streamFactory: $factory,
            );
        });
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../routes/emitwave.php');
        $this->publishes([__DIR__.'/../config/emitwave.php' => config_path('emitwave.php')], 'emitwave-config');

        Broadcast::extend('emitwave', function ($app, array $config) {
            foreach (['gateway_url', 'app_id', 'key', 'secret'] as $required) {
                if (empty($config[$required])) {
                    throw new \InvalidArgumentException("Emitwave broadcasting connection is missing required config: {$required}");
                }
            }
            $factory = new Psr17Factory;
            $http = $app->bound(ClientInterface::class) ? $app->make(ClientInterface::class) : new CurlHttpClient($factory);

            return new EmitwaveBroadcaster(new Client(
                appId: $config['app_id'],
                publicKey: $config['key'],
                secret: $config['secret'],
                gatewayUrl: $config['gateway_url'],
                http: $http,
                requestFactory: $factory,
                streamFactory: $factory,
            ));
        });
    }
}
