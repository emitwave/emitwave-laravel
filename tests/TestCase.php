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
}
