<?php

namespace Alyakin\DictionaryCache\Services;

use Alyakin\DictionaryCache\Adapters\IlluminateRedisClient;
use Alyakin\DictionaryCache\Adapters\PhpRedisClient;
use Alyakin\DictionaryCache\Contracts\RedisClientInterface;

/**
 * Dictionary Cache Service for managing cached data in Redis.
 *
 * This service provides efficient storage and retrieval of dictionary-style
 * data sets using Redis sets (`sadd`, `sinter`, etc.).
 * It also manages TTL for cached entries and supports cache warming (`preload()`).
 *
 * ## Example usage:
 * ```php
 * $cache = new DictionaryCacheService('user_123', fn() => ['item1', 'item2']);
 * $cache->addItems(['item3', 'item4'])->keepAlive();
 * ```
 *
 * @author Your Name
 * @license MIT
 */
class DictionaryCacheService
{
    protected const DEFAULT_TTL = 3600;

    private const MAX_CONTEXT_LENGTH = 1000;

    private const MAX_KEY_LENGTH = 24;

    protected ?string $cacheKey = null;

    protected ?\Closure $dataProvider = null;

    protected RedisClientInterface $redisClient;

    /**
     * DictionaryCacheService constructor.
     *
     * Initializes the Redis connection, sets the context and TTL if provided,
     * and assigns a data provider for populating the cache.
     *
     * @param  string|null  $contextId  Optional unique identifier for the context.
     *                                  If provided, it will generate the cache key.
     * @param  \Closure|null  $dataProvider  Optional callback to provide data for caching.
     * @param  RedisClientInterface|\Redis|\RedisCluster|\Illuminate\Redis\Connections\Connection|null  $redisInstance
     *                                                                                                                  Optional Redis client or adapter. When null, the service will attempt to auto-detect a Laravel Redis connection.
     *
     * @throws \InvalidArgumentException If an invalid Redis instance is provided.
     */
    public function __construct(?string $contextId = null, ?\Closure $dataProvider = null, $redisInstance = null)
    {
        $this->redisClient = $this->resolveRedisClient($redisInstance);

        if ($contextId) {
            $this->setContext($contextId);
            $this->setTTL($this->getTTL()); // Read TTL from Redis if exists, otherwise set DEFAULT_TTL
        }

        if ($dataProvider) {
            $this->setDataProvider($dataProvider);
        }
    }

    /**
     * Sets the context ID and generates the cache key.
     *
     * @param  string  $contextId  Unique identifier for the context.
     * @param  string  $key  Identifier prefix for the cache key (default: "dictionary").
     *
     * @throws \InvalidArgumentException If the context ID or key is invalid.
     */
    public function setContext(string $contextId, string $key = 'dictionary'): self
    {
        $contextId = trim($contextId);
        $key = trim($key);

        if ($contextId === '' || strlen($contextId) > self::MAX_CONTEXT_LENGTH) {
            throw new \InvalidArgumentException('Invalid context ID: must be 1-'.self::MAX_CONTEXT_LENGTH.' characters long.');
        }

        if ($key === '' || strlen($key) > self::MAX_KEY_LENGTH) {
            throw new \InvalidArgumentException('Invalid key: must be 1-'.self::MAX_KEY_LENGTH.' characters long.');
        }

        if (! preg_match('/^[a-zA-Z0-9_-]+$/', $contextId)) {
            throw new \InvalidArgumentException('Context ID contains invalid characters. Allowed: a-z, A-Z, 0-9, _, -');
        }

        if (! preg_match('/^[a-zA-Z0-9_-]+$/', $key)) {
            throw new \InvalidArgumentException('Key contains invalid characters. Allowed: a-z, A-Z, 0-9, _, -');
        }

        $this->cacheKey = "{$key}_{$contextId}";

        return $this;
    }

    /**
     * Sets a data provider function for populating the cache.
     *
     * The provided Closure must return an array of items to be stored in Redis.
     *
     * @param  \Closure  $dataProvider  Function that returns an array of items.
     */
    public function setDataProvider(\Closure $dataProvider): self
    {
        $this->dataProvider = $dataProvider;

        return $this;
    }

    /**
     * Sets the time-to-live (TTL) for the cache key.
     *
     * Stores the TTL separately in Redis under a `{cacheKey}_ttl` key.
     * If the provided TTL is less than 1 second, an exception is thrown.
     *
     * @param  int  $ttl  The TTL value in seconds.
     *
     * @throws \InvalidArgumentException If TTL is less than 1 second.
     */
    public function setTTL(int $ttl): self
    {
        $cacheKey = $this->requireCacheKey();

        if ($ttl < 1) {
            throw new \InvalidArgumentException('TTL must be at least 1 second.');
        }

        $this->redisClient->set($cacheKey.'_ttl', $ttl);
        if ($this->redisClient->exists($cacheKey)) {
            $this->redisClient->expire($cacheKey, $ttl);
        }

        return $this;
    }

    /**
     * Retrieves the time-to-live (TTL) for the cache key.
     *
     * If the TTL is not set in Redis, the default TTL is returned.
     *
     * @return int The TTL value in seconds.
     */
    public function getTTL(): int
    {
        $cacheKey = $this->requireCacheKey();
        $storedTtl = $this->redisClient->get($cacheKey.'_ttl');
        $ttl = is_numeric($storedTtl) ? (int) $storedTtl : self::DEFAULT_TTL;

        if ($ttl < 1) {
            throw new \InvalidArgumentException('TTL must be at least 1 second.');
        }

        return $ttl;
    }

    /**
     * Loads data into the cache if it is not already initialized.
     *
     * Uses the `dataProvider` closure to fetch data and store it in Redis.
     * If cache does not exist, the method retrieves the data, validates it,
     * and stores it as a Redis set, updating the TTL.
     *
     * @throws \RuntimeException If the data provider is not set.
     * @throws \UnexpectedValueException If the data provider does not return an array.
     */
    public function preload(): void
    {
        $cacheKey = $this->requireCacheKey();

        if (! $this->dataProvider) {
            throw new \RuntimeException('DataProvider is not set. Use setDataProvider before.');
        }

        if (! $this->redisClient->exists($cacheKey)) {
            $data = ($this->dataProvider)();

            if (! is_array($data)) {
                throw new \UnexpectedValueException('Data provider must return an array.');
            }

            if (! empty($data)) {
                $items = $this->normalizeValues($data);
                $this->redisClient->sadd($cacheKey, ...$items);
                $this->keepAlive();
            }
        }
    }

    /**
     * Checks if a specific item exists in the cache set.
     * Ensures the cache is initialized before performing the check.
     *
     * @param  string  $itemId  The item ID to check.
     * @return bool `true` if the item exists in the Redis set, `false` otherwise.
     */
    public function hasItem(string $itemId): bool
    {
        $this->ensureCacheInitialized();
        $cacheKey = $this->requireCacheKey();

        return $this->redisClient->sismember($cacheKey, $itemId);
    }

    /**
     * Checks which items from the given list exist in the cache set.
     *
     * If the cache set does not exist, the method returns an empty array.
     * Otherwise, it creates a temporary Redis set and uses `sinter()` to find matches.
     * The temporary set is deleted after the operation.
     *
     * @param  string[]  $itemIds  The list of item IDs to check.
     * @return string[] The list of existing item IDs.
     */
    public function hasItems(array $itemIds): array
    {
        $this->ensureCacheInitialized();

        $cacheKey = $this->requireCacheKey();

        if (! $this->redisClient->exists($cacheKey) || empty($itemIds)) {
            return [];
        }

        $tempKey = "tmp_{$cacheKey}_".bin2hex(random_bytes(8));
        $existingIds = [];

        try {
            $items = $this->normalizeValues($itemIds);
            $this->redisClient->sadd($tempKey, ...$items);
            $this->redisClient->expire($tempKey, 5);
            $existingIds = $this->redisClient->sinter($cacheKey, $tempKey);
        } finally {
            $this->redisClient->del($tempKey);
        }

        return $existingIds;
    }

    /**
     * Retrieves all items stored in the cache set.
     *
     * If the cache key does not exist, an empty array is returned.
     *
     * @return string[] The list of all cached items.
     */
    public function getAllItems(): array
    {
        $cacheKey = $this->requireCacheKey();
        $this->ensureCacheInitialized();

        return $this->redisClient->smembers($cacheKey);
    }

    /**
     * Checks if the cache key exists in the cache set.
     *
     * @return bool `true` if the cache key exists, `false` otherwise.
     */
    public function exists(): bool
    {
        $cacheKey = $this->requireCacheKey();

        return $this->redisClient->exists($cacheKey);
    }

    /**
     * Adds multiple items to the cache set.
     *
     * If the provided array is empty, the method does nothing.
     *
     * @param  string[]  $items  The list of items to add.
     */
    public function addItems(array $items): self
    {
        $cacheKey = $this->requireCacheKey();
        $this->ensureCacheInitialized();

        if (! empty($items)) {
            $values = $this->normalizeValues($items);
            $this->redisClient->sadd($cacheKey, ...$values);
        }

        return $this;
    }

    /**
     * Removes a single item from the cache set.
     *
     * If the item does not exist, the method does nothing.
     *
     * @param  string  $item  The item ID to remove.
     */
    public function removeItem(string $item): self
    {
        $cacheKey = $this->requireCacheKey();
        $this->ensureCacheInitialized();

        if ($item !== '') {
            $this->redisClient->srem($cacheKey, $item);
        }

        return $this;
    }

    /**
     * Keeps the cache entry alive by resetting its expiration time (TTL).
     *
     * This method does not change the TTL value but refreshes its countdown.
     *
     * @throws \InvalidArgumentException If the retrieved TTL is invalid (less than 1 second).
     */
    public function keepAlive(): self
    {
        $cacheKey = $this->requireCacheKey();
        $this->ensureCacheInitialized();

        $ttl = $this->getTTL();

        if ($ttl < 1) {
            throw new \InvalidArgumentException('TTL must be at least 1 second.');
        }

        $this->redisClient->expire($cacheKey, $ttl);

        return $this;
    }

    /**
     * Clears the cached data for the current context.
     *
     * This does not remove TTL settings, only the cached items.
     *
     * @throws \Exception If the cache key is not set.
     */
    public function clear(): void
    {
        $cacheKey = $this->requireCacheKey();
        $this->redisClient->del($cacheKey);
    }

    /**
     * Ensures that the cache is initialized.
     *
     * If the cache key does not exist in Redis, it will be populated using `preload()`.
     */
    protected function ensureCacheInitialized(): void
    {
        $cacheKey = $this->requireCacheKey();

        if ($this->redisClient->exists($cacheKey) || ! $this->dataProvider) {
            return;
        }

        $this->preload();
    }

    /**
     * Ensures that a cache key is set before executing operations that require it.
     *
     * @throws \InvalidArgumentException If the cache key is not defined.
     */
    private function requireCacheKey(): string
    {
        if (! $this->cacheKey) {
            $caller = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1]['function'] ?? 'unknown method';
            throw new \InvalidArgumentException("Undefined cacheKey. Use setContext() before calling {$caller}().");
        }

        return $this->cacheKey;
    }

    /**
     * Checks if a cache key is set.
     *
     * @return bool `true` if a cache key is defined, `false` otherwise.
     */
    public function hasCacheKey(): bool
    {
        return isset($this->cacheKey);
    }

    /**
     * @param  array<mixed>  $values
     * @return string[]
     */
    private function normalizeValues(array $values): array
    {
        return array_map(
            static function (mixed $value): string {
                if (is_string($value) || is_int($value) || is_float($value) || is_bool($value) || $value === null) {
                    return (string) $value;
                }

                if ($value instanceof \Stringable) {
                    return (string) $value;
                }

                throw new \UnexpectedValueException('Cache values must be scalar or stringable.');
            },
            $values
        );
    }

    /**
     * @param  mixed  $redisInstance
     */
    private function resolveRedisClient($redisInstance): RedisClientInterface
    {
        if ($redisInstance instanceof RedisClientInterface) {
            return $redisInstance;
        }

        if ($redisInstance instanceof \Redis || $redisInstance instanceof \RedisCluster) {
            return new PhpRedisClient($redisInstance);
        }

        if ($redisInstance !== null
            && class_exists(\Illuminate\Redis\Connections\Connection::class)
            && $redisInstance instanceof \Illuminate\Redis\Connections\Connection
        ) {
            return new IlluminateRedisClient($redisInstance);
        }

        if ($redisInstance === null) {
            $autoDetected = $this->detectDefaultRedisClient();

            if ($autoDetected !== null) {
                return $autoDetected;
            }

            throw new \InvalidArgumentException(
                'Redis client is required. Provide RedisClientInterface, native \Redis instance, or Laravel Redis connection.'
            );
        }

        throw new \InvalidArgumentException(sprintf(
            'Unsupported Redis client instance: %s',
            get_debug_type($redisInstance)
        ));
    }

    private function detectDefaultRedisClient(): ?RedisClientInterface
    {
        if (! class_exists(\Illuminate\Support\Facades\Redis::class)
            || ! class_exists(\Illuminate\Redis\Connections\Connection::class)
        ) {
            return null;
        }

        $connection = \Illuminate\Support\Facades\Redis::connection();

        return new IlluminateRedisClient($connection);
    }
}
