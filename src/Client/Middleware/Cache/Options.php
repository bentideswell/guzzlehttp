<?php

namespace FishPig\GuzzleHttp\Client\Middleware\Cache;

class Options
{
    /**
     * @var CacheInterface
     */
    private $data = [];

    /**
     *
     */
    public function __construct(...$optionsCollection)
    {
        $this->data = [];
        foreach ($optionsCollection as $options) {
            $this->data = array_merge($this->data, $options);
        }
    }

    /**
     *
     */
    public function isEnabled(): bool
    {
        return (int)($this->data['enabled'] ?? false) === 1;
    }

    /**
     *
     */
    public function getTtl(): ?int
    {
        return array_key_exists('ttl', $this->data) ? $this->data['ttl'] : 0;
    }

    /**
     *
     */
    public function getSalt(): string
    {
        return $this->data['salt'] ?? '';
    }

    /**
     *
     */
    public function canBypassRequest(): bool
    {
        return (int)($this->data['bypass'] ?? false) === 1;
    }

    /**
     *
     */
    public function getCacheableStatusCodes(): array
    {
        return $this->data['cacheableStatusCodes'] ?? [200];
    }

    /**
     *
     */
    public function isStatusCacheable($status): bool
    {
        $codes = $this->getCacheableStatusCodes();
        return in_array('*', $codes) || in_array($status, $codes);
    }
}
