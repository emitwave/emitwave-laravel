# `emitwave/laravel`

Laravel broadcasting driver backed by Emitwave.

## Requirements

- PHP 8.3 or newer
- Laravel 12 or 13 broadcasting components
- `emitwave/php`
- An Emitwave application ID and active server credential

The service provider is discovered automatically by Laravel.

## Installation

While developing from this monorepo, add both local packages to the consuming
application's `composer.json`:

```json
{
  "repositories": [
    { "type": "path", "url": "../packages/php-sdk" },
    { "type": "path", "url": "../packages/laravel-sdk" }
  ],
  "require": {
    "emitwave/laravel": "@dev"
  }
}
```

Then run:

```bash
composer update emitwave/laravel
```

After a public release, use `composer require emitwave/laravel`.

## Configure broadcasting

Add an Emitwave connection to `config/broadcasting.php`:

```php
'connections' => [
    'emitwave' => [
        'driver' => 'emitwave',
        'gateway_url' => env('EMITWAVE_GATEWAY_URL', 'https://api.emitwave.com'),
        'app_id' => env('EMITWAVE_APP_ID'),
        'key' => env('EMITWAVE_KEY'),
        'secret' => env('EMITWAVE_SECRET'),
    ],
],
```

Set the environment variables:

```dotenv
BROADCAST_CONNECTION=emitwave
EMITWAVE_GATEWAY_URL=https://api.emitwave.com
EMITWAVE_APP_ID=app_your_application_id
EMITWAVE_KEY=ew_pk_live_your_public_key
EMITWAVE_SECRET=your_server_secret
```

Keep `EMITWAVE_SECRET` outside source control and browser-visible environment
variables.

## Authentication boundaries

The broadcasting driver only handles trusted server publishing:

- `EMITWAVE_KEY` and `EMITWAVE_SECRET` sign publish requests;
- the browser public application key identifies the app during connection;
- an end-user Bearer token authenticates the browser to the control plane;
- the control plane issues connection and subscription tokens;
- private/presence authorization callbacks are evaluated by the Emitwave
  control plane, not by this broadcaster.

Calling the broadcaster's `auth()` methods therefore raises an exception. Point
browser SDKs at the Emitwave `/connect-token` and `/channel-auth` endpoints.

## Channel types

Laravel channel names are forwarded to Emitwave without changing their
external prefixes:

| Emitwave channel | Laravel broadcast target | Subscriber authorization |
|---|---|---|
| Public | `new Channel('news')` | Connection token only |
| Private | `new PrivateChannel('account.42')` | Truthy control-plane callback for `private-account.42` |
| Presence | `new PresenceChannel('room.lobby')` | Control-plane callback returning `{ id, info }` |
| Public cache | `new Channel('cache-prices')` | Connection token only |
| Private cache | `new Channel('private-cache-account.42')` | Truthy private callback |
| Presence cache | `new Channel('presence-cache-room.lobby')` | Presence callback returning `{ id, info }` |
| Private encrypted | `new Channel('private-encrypted-account.42')` | Private callback and shared decryption key |

The driver currently publishes payloads unchanged. It does not create the
ciphertext envelope for private-encrypted channels; use a compatible trusted
encryption publisher before broadcasting to that channel type.

## Broadcast an event

Use Laravel's normal broadcasting contracts:

```php
<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class OrderCreated implements ShouldBroadcast
{
    public function __construct(public array $order) {}

    public function broadcastOn(): array
    {
        return [new Channel('orders')];
    }

    public function broadcastAs(): string
    {
        return 'order.created';
    }
}
```

Dispatch it normally:

```php
OrderCreated::dispatch(['id' => 'ord_123', 'total' => 4900]);
```

Laravel private and presence channel names are forwarded with their prefixes.
Browser subscription authorization is handled by Emitwave's control-plane
channel-auth endpoint, not by this broadcaster's `auth()` method.

If a PSR-18 `ClientInterface` is bound in Laravel's container, the driver uses
it. Otherwise it uses the cURL transport from `emitwave/php`.

## Development

```bash
composer install
./vendor/bin/phpunit
```

