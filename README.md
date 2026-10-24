# `emitwave/laravel`

Laravel broadcasting driver and same-origin browser authentication endpoints backed by Emitwave.

## Requirements

- PHP 8.3 or newer
- Laravel 12 or 13 broadcasting components
- `emitwave/php`
- An Emitwave application ID and active server credential

The service provider is discovered automatically by Laravel.

## Installation

```bash
composer require emitwave/laravel
```

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

Publish the package configuration when you need to change the route prefix or middleware:

```bash
php artisan vendor:publish --tag=emitwave-config
```

## Browser authentication

The package registers two same-origin POST routes:

- `/emitwave/connect-token` authenticates the current Laravel user and returns a short-lived connection token.
- `/emitwave/channel-auth` evaluates your channel callback and returns a scoped subscription token.

The default middleware is `['web', 'auth']`. For Sanctum APIs or another authentication stack, publish the config and set the middleware used by your application.

Register private and presence channel rules during application boot:

```php
use Emitwave\Laravel\Facades\Emitwave;

Emitwave::channel('private-account.{account}', function ($user, string $account): bool {
    return $user->accounts()->whereKey($account)->exists();
});

Emitwave::channel('presence-room.{room}', fn ($user, string $room): array => [
    'id' => (string) $user->getAuthIdentifier(),
    'info' => ['name' => $user->name],
]);
```

The browser calls these endpoints on your own Laravel origin. Laravel owns user authentication and authorization; the package makes a signed server-to-server request to Emitwave to mint the final realtime token.
The connection endpoint forwards the browser's `Origin` inside that signed
request, allowing Emitwave to enforce the application's configured origin list
without maintaining a global WebSocket-domain list.
An empty allowed-origin list accepts every origin. Once the application has at
least one configured origin, only exact configured origins are accepted.

## Security boundaries

The broadcasting driver only handles trusted server publishing:

- `EMITWAVE_KEY` and `EMITWAVE_SECRET` sign publish requests;
- the browser public application key identifies the app during connection;
- the browser authenticates to your Laravel application using its normal session or API authentication;
- channel callbacks execute inside your Laravel application;
- the package signs token-mint requests with the server credential;
- the global realtime signing secret remains inside Emitwave.

Calling the broadcaster's legacy `auth()` methods raises an exception. The browser SDK uses the package routes above instead.

## Channel types

Laravel channel names are forwarded to Emitwave without changing their
external prefixes:

| Emitwave channel | Laravel broadcast target | Subscriber authorization |
|---|---|---|
| Public | `new Channel('news')` | Connection token only |
| Private | `new PrivateChannel('account.42')` | Truthy host-application callback for `private-account.42` |
| Presence | `new PresenceChannel('room.lobby')` | Host callback returning `{ id, info }` |
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
Browser subscription authorization is handled by the package's same-origin
`/emitwave/channel-auth` route, not by the broadcaster's `auth()` method.

If a PSR-18 `ClientInterface` is bound in Laravel's container, the driver uses
it. Otherwise it uses the cURL transport from `emitwave/php`.

## Development

```bash
composer install
./vendor/bin/phpunit
```

