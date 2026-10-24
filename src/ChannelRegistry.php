<?php

namespace Emitwave\Laravel;

class ChannelRegistry
{
    /** @var array<array{regex:string, callback:callable}> */
    private array $patterns = [];

    public function register(string $pattern, callable $callback): void
    {
        $regex = '';
        $offset = 0;
        if (preg_match_all('/\{(\w+)\}/', $pattern, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[0] as [$token, $position]) {
                $regex .= preg_quote(substr($pattern, $offset, $position - $offset), '/').'([^.]+)';
                $offset = $position + strlen($token);
            }
        }
        $regex .= preg_quote(substr($pattern, $offset), '/');
        $this->patterns[] = ['regex' => '/^'.$regex.'$/', 'callback' => $callback];
    }

    public function resolve(string $channel, mixed $user): mixed
    {
        foreach ($this->patterns as $pattern) {
            if (preg_match($pattern['regex'], $channel, $matches)) {
                return ($pattern['callback'])($user, ...array_slice($matches, 1));
            }
        }

        return null;
    }
}
