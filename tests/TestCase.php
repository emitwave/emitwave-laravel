<?php

namespace Emitwave\Laravel\Tests;

use Emitwave\Laravel\EmitwaveServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [EmitwaveServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('emitwave.gateway_url', 'http://gateway.test');
        $app['config']->set('emitwave.app_id', 'app_1');
        $app['config']->set('emitwave.key', 'ew_pk_test_key');
        $app['config']->set('emitwave.secret', 'ew_sk_test_secret');
        $app['config']->set('emitwave.middleware', [TestUserMiddleware::class]);
    }
}
