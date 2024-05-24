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
        $this->options = $options;
    }

    /**
     *
     */
    public function __invoke(callable $handler): callable
    {
        return function (RequestInterface $request, array $options) use (&$handler) {
            $localOptions = (new Cache\Options($this->options, $options['cache']));

            if (!$localOptions->isEnabled()) {
                return $handler($request, $options);
            }

            $key = $this->getKey($request->getUri(), $localOptions->getSalt());

            if ($localOptions->canBypassRequest() === false) {
                if (($entry = $this->get($key)) !== null) {
                   return $entry;
                }
            }

            $promise = $handler($request, $options);

            return $promise->then(
                function (ResponseInterface $response) use ($localOptions, $key) {
                    if (!$response) {
                        return $response;
                    }

                    if (!$localOptions->isStatusCacheable($response->getStatusCode())) {
                        return $response;
                    }

                    $this->save($key, $response, $localOptions->getTtl());

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

        return new FulfilledPromise(
            new Response(200, [], $entry)
        );
    }

    /**
     *
     */
    protected function save(string $key, ?ResponseInterface $response, ?int $ttl): bool
    {
        $responseBody = (string)$response->getBody();
        $saved = $this->cache->set($key, $responseBody, $ttl);

        if ($response->getBody()->isSeekable()) {
            $response->getBody()->rewind();
        }

        return $saved;
    }
}
