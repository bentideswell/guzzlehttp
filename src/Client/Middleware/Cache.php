<?php

namespace FishPig\GuzzleHttp\Client\Middleware;

use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;
use Psr\SimpleCache\CacheInterface;

class Cache
{
    public const TTL_DEFAULT = null;

    /**
     * @var CacheInterface
     */
    private $cache;

    /**
     *
     */
    private $options = [];

    /**
     *
     */
    public function __construct(CacheInterface $cache, $options = [])
    {
        $this->cache = $cache;
        $this->options = array_merge(
            [
                'enabled' => true,
                'ttl' => self::TTL_DEFAULT,
                'salt' => '',
                'bypass' => false,
                'cacheableStatusCodes' => $this->getDefaultCacheableStatusCodes()
            ],
            $options
        );
    }

    /**
     *
     */
    public function __invoke(callable $handler): callable
    {
        return function (RequestInterface $request, array $options) use (&$handler) {
            // Merge original options and local options
            $localOptions = array_merge(
                $this->options,
                $options['cache'] ?? []
            );

            // Ensure we have some base values in the correct format
            $localOptions['ttl'] = $localOptions['ttl'] ?: null;

            // Cache can be disabled so check here
            if ((int)$localOptions['enabled'] !== 1) {
                return $handler($request, $options);
            }

            // Filter out disallowed chars and apply salt, if configured
            $key = $this->getKey($request->getUri(), $localOptions['salt'] ?? '');

            // If bypass, do not load from cache.
            if ((int)$localOptions['bypass'] !== 1) {
                // Bypass not set so lets check the cache
                if (($entry = $this->get($key)) !== null) {
                    return $entry;
                }
            }

            // Not in cache so lets run the request...
            return $handler($request, $options)->then(
                function (ResponseInterface $response) use ($localOptions, $key) {
                    if ($response) {
                        $isStatusCacheable = array_intersect(
                            ['*', $response->getStatusCode()],
                            $localOptions['cacheableStatusCodes']
                        );

                        if ($isStatusCacheable) {
                            $this->save($key, $response, $localOptions['ttl']);
                        }
                    }

                    return $response;
                }
            );
        };
    }

    /**
     *
     */
    protected function getKey(UriInterface $uri, string $prefix = ''): string
    {
        return preg_replace('/[{}()\/\@:]/', '-', $prefix . $uri);
    }

    /**
     *
     */
    protected function get(string $key): ?FulfilledPromise
    {
        if (($entry = $this->cache->get($key)) === null) {
            return $entry;
        }

        $entry = json_decode($entry, true, 512, JSON_THROW_ON_ERROR);

        return new FulfilledPromise(
            new Response((int)$entry['status'], [], $entry['body'])
        );
    }

    /**
     *
     */
    protected function save(string $key, ?ResponseInterface $response, ?int $ttl): bool
    {
        $responseBody = json_encode([
            'status' => $response->getStatusCode(),
            'body' => (string)$response->getBody()
        ]);

        $saved = $this->cache->set($key, $responseBody, $ttl);

        if ($response->getBody()->isSeekable()) {
            $response->getBody()->rewind();
        }

        return $saved;
    }

    /**
     *
     */
    public static function getDefaultCacheableStatusCodes(): array
    {
        return [200];
    }
}
