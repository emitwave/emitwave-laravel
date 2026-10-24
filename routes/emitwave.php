<?php

use Emitwave\Laravel\Http\Controllers\ChannelAuthController;
use Emitwave\Laravel\Http\Controllers\ConnectTokenController;
use Illuminate\Support\Facades\Route;

Route::prefix(config('emitwave.route_prefix', 'emitwave'))
    ->middleware(config('emitwave.middleware', ['web', 'auth']))
    ->group(function (): void {
        Route::post('/connect-token', ConnectTokenController::class)->name('emitwave.connect-token');
        Route::post('/channel-auth', ChannelAuthController::class)->name('emitwave.channel-auth');
    });
