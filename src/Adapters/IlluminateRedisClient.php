<?php

namespace Alyakin\DictionaryCache\Adapters;

use Alyakin\DictionaryCache\Contracts\RedisClientInterface;
use Illuminate\Redis\Connections\Connection as RedisConnection;

/**
 * Adapter that wraps Illuminate Redis connections.
 */
class IlluminateRedisClient implements RedisClientInterface
{
    public function __construct(private RedisConnection $connection) {}

    public function exists(string $key): bool
    {
        return (bool) $this->connection->exists($key);
    }

    public function sadd(string $key, string ...$members): void
    {
        if (empty($members)) {
            return;
        }

        $this->connection->sadd($key, ...$members);
    }

    public function smembers(string $key): array
    {
        return array_values($this->connection->smembers($key));
    }

    public function sismember(string $key, string $member): bool
    {
        return (bool) $this->connection->sismember($key, $member);
    }

    public function sinter(string $key, string $otherKey): array
    {
        return array_values($this->connection->sinter($key, $otherKey));
    }

    public function srem(string $key, string $member): void
    {
        $this->connection->srem($key, $member);
    }

    public function expire(string $key, int $ttl): void
    {
        $this->connection->expire($key, $ttl);
    }

    public function set(string $key, int $value): void
    {
        $this->connection->set($key, $value);
    }

    public function get(string $key): ?string
    {
        $result = $this->connection->get($key);

        return $result === null ? null : (string) $result;
    }

    public function del(string ...$keys): void
    {
        if (empty($keys)) {
            return;
        }

        $this->connection->del(...$keys);
    }

    public function getConnection(): RedisConnection
    {
        return $this->connection;
    }
}
