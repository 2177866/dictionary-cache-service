<?php

namespace Alyakin\DictionaryCache\Adapters;

use Alyakin\DictionaryCache\Contracts\RedisClientInterface;

/**
 * Adapter for the native PHP Redis extension.
 */
class PhpRedisClient implements RedisClientInterface
{
    /**
     * @var \Redis|\RedisCluster
     */
    private $connection;

    /**
     * @param  \Redis|\RedisCluster  $connection
     */
    public function __construct($connection)
    {
        if (! ($connection instanceof \Redis) && ! ($connection instanceof \RedisCluster)) {
            throw new \InvalidArgumentException('PhpRedisClient expects an instance of \Redis or \RedisCluster.');
        }

        $this->connection = $connection;
    }

    public function exists(string $key): bool
    {
        return (bool) $this->connection->exists($key);
    }

    public function sadd(string $key, string ...$members): void
    {
        if (empty($members)) {
            return;
        }

        $this->connection->sAdd($key, ...$members);
    }

    public function smembers(string $key): array
    {
        return array_values($this->connection->sMembers($key));
    }

    public function sismember(string $key, string $member): bool
    {
        return (bool) $this->connection->sIsMember($key, $member);
    }

    public function sinter(string $key, string $otherKey): array
    {
        return array_values($this->connection->sInter($key, $otherKey));
    }

    public function srem(string $key, string $member): void
    {
        $this->connection->sRem($key, $member);
    }

    public function expire(string $key, int $ttl): void
    {
        $this->connection->expire($key, $ttl);
    }

    public function set(string $key, int $value): void
    {
        $this->connection->set($key, (string) $value);
    }

    public function get(string $key): ?string
    {
        $result = $this->connection->get($key);

        return is_string($result) ? $result : null;
    }

    public function del(string ...$keys): void
    {
        if (empty($keys)) {
            return;
        }

        $this->connection->del(...$keys);
    }
}
