<?php

namespace Emitwave\Laravel;

class Emitwave
{
    public function __construct(private readonly ChannelRegistry $channels) {}

    public function channel(string $pattern, callable $callback): void
    {
        $this->channels->register($pattern, $callback);
    }
}
