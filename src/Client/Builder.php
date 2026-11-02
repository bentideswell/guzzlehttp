<?php

namespace FishPig\GuzzleHttp\Client;

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use Symfony\Component\Cache\Adapter\Psr16Adapter;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Psr16Cache;
use FishPig\GuzzleHttp\Client\Middleware\Cache as CacheMiddleware;

class Builder
{
    /**
     *
     */
    private $config = [];

    /**
     *
     */
    public function withCache(array $config = []): self
    {
        if (!isset($this->config['cache'])) {
            $this->config['cache'] = [
                'enabled' => true
            ];
            // Set defaults
            $this->withCacheNamespace()
                ->withCacheTtl()
                ->withCacheDirectory()
                ->withCacheableStatusCodes();
        }

        $this->config['cache'] = array_merge(
            $this->config['cache'],
            ['enabled' => true],
            $config
        );

        return $this;
    }

    /**
     *
     */
    public function withCacheNamespace(string $ns = ''): self
    {
        return $this->withCache(['namespace' => $ns]);
    }

    /**
     *
     */
    public function withCacheTtl(?int $ttl = CacheMiddleware::TTL_DEFAULT): self
    {
        return $this->withCache(['ttl' => $ttl]);
    }

    /**
     *
     */
    public function withCacheDirectory(string $directory = ''): self
    {
        return $this->withCache(['directory' => $directory]);
    }

    /**
     *
     */
    public function withCacheableStatusCodes(?array $codes = null): self
    {
        return $this->withCache([
            'cacheableStatusCodes' => $codes ?? CacheMiddleware::getDefaultCacheableStatusCodes()
        ]);
    }

    /**
     *
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     *
     */
    public function build($config): \GuzzleHttp\Client
    {
        if (is_string($config)) {
            $config = ['base_uri' => $config];
        }

        $config = array_merge($this->getConfig(), $config);

        if (isset($config['cache']['enabled']) && (int)$config['cache']['enabled'] === 1) {
            $cacheStorage = new Psr16Cache(
                new FilesystemAdapter(
                    $config['cache']['namespace'] ?? '',
                    $config['cache']['ttl'] ?? 0,
                    $config['cache']['directory'] ?? null
                )
            );

            $stack = $config['handler'] ?? HandlerStack::create();
            $stack->push(new Middleware\Cache($cacheStorage, $this->config['cache'] ?? []), 'cache');
            $config['handler'] = $stack;
        }

        return new Client($config);
    }
}
