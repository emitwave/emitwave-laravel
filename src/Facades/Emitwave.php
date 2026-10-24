<?php

namespace Emitwave\Laravel\Facades;

use Illuminate\Support\Facades\Facade;

class Emitwave extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Emitwave\Laravel\Emitwave::class;
    }
}
