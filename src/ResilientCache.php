<?php
namespace f2r\ResilientCache;

use Psr\SimpleCache\CacheInterface;

class ResilientCache
{
    const CACHE_STATUS_UNKNOWN = -1;
    const CACHE_STATUS_NOT_FOUND = 0;
    const CACHE_STATUS_FOUND_NOT_EXPIRED = 1;
    const CACHE_STATUS_FOUND_EXPIRED = 2;
    const CACHE_STATUS_RESILIENCED = 3;

    /** @var CacheInterface $adapter */
    private $adapter;
    /** @var int $retry */
    private $retry;
    /** @var int $resilience */
    private $resilience;
    /** @var string $defaultKey */
    private $defaultKey;
    /** @var int $defaultTtl */
    private $defaultTtl;
    /** @var int $time */
    private $time;
    /** @var int */
    private $lastCacheStatus;

    /**
     * ResilientCache constructor.
     *
     * @param CacheInterface $adapter
     * @param int|null $resilienceTtl Keeps data, even outdated, for a period of resilience (null: Infinite duration)
     * @param int $retryTtl When error, use a short TTL
     */
    public function __construct(CacheInterface $adapter, int $resilienceTtl = null, int $retryTtl = 0)
    {
        $this->adapter = $adapter;
        $this->resilience = $resilienceTtl;
        $this->retry = $retryTtl;
        $this->time = null;
        $this->defaultKey = null;
        $this->defaultTtl = null;
        $this->lastCacheStatus = self::CACHE_STATUS_UNKNOWN;
    }

    /**
     * @param int $time
     *
     * @return $this
     */
    public function setTime(int $time)
    {
        $this->time = $time;
        return $this;
    }

    /**
     * @param string $key
     *
     * @return $this
     */
    public function setDefaultKey(string $key)
    {
        $this->defaultKey = $key;
        return $this;
    }

    /**
     * @param int $ttl
     *
     * @return $this
     */
    public function setDefaultTtl(int $ttl)
    {
        $this->defaultTtl = $ttl;
        return $this;
    }

    /**
     * @return int
     */
    public function getLastCacheStatus()
    {
        return $this->lastCacheStatus;
    }

    /**
     * @param callable $callable
     * @param string $key
     * @param int $ttl
     *
     * @return |null
     * @throws \Psr\SimpleCache\InvalidArgumentException
     * @throws \Throwable
     */
    public function that(callable $callable, string $key = null, int $ttl = null)
    {
        $this->lastCacheStatus = self::CACHE_STATUS_UNKNOWN;
        $value = null;
        $expire = null;
        $time = $this->time ?? time();
        if ($key === null) {
            $key = $this->defaultKey;
        }
        if ($key === null) {
            throw new InvalidArgumentException("Invalid cache key. You must define at least a default key value");
        }
        if ($ttl === null) {
            $ttl = $this->defaultTtl;
        }
        if ($ttl === null or $ttl <= 0) {
            throw new InvalidArgumentException("Invalid cache TTL. You must define at least a default TTL value greater than 0");
        }

        $this->lastCacheStatus = self::CACHE_STATUS_NOT_FOUND;
        if ($this->adapter->has($key)) {
            [$expire, $value] = $this->adapter->get($key);
            if ($expire >= $time) {
                $this->lastCacheStatus = self::CACHE_STATUS_FOUND_NOT_EXPIRED;
                return $value;
            }
            $this->lastCacheStatus = self::CACHE_STATUS_FOUND_EXPIRED;
        }

        try {
            $value = $callable();
        } catch (\Throwable $exception) {
            if ($this->lastCacheStatus === self::CACHE_STATUS_NOT_FOUND) {
                throw $exception;
            }
            $this->lastCacheStatus = self::CACHE_STATUS_RESILIENCED;
            $ttl = $this->retry;
        }

        if ($ttl > 0) {
            $this->adapter->set($key, [$time + $ttl, $value], $this->resilience);
        }

        return $value;
    }
}
