<?php

namespace FishPig\GuzzleHttp\Client;

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use Symfony\Component\Cache\Adapter\Psr16Adapter;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Psr16Cache;

class Builder
{
    /**
     *
     */
    public static function withCache(array $config)
    {
        $cacheStorage = new Psr16Cache(
            new FilesystemAdapter(
                $config['cache']['namespace'] ?? '',
                $config['cache']['ttl'] ?? 0,
                $config['cache']['directory'] ?? null
            )
        );

        $stack = HandlerStack::create();
        $stack->push(new Middleware\Cache($cacheStorage, $config['cache'] ?? []), 'cache');
        $config['handler'] = $stack;
        return new Client($config);
    }
}
