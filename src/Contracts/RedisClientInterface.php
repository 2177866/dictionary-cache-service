<?php

namespace Alyakin\DictionaryCache\Contracts;

interface RedisClientInterface
{
    public function exists(string $key): bool;

    public function sadd(string $key, string ...$members): void;

    /**
     * @return string[]
     */
    public function smembers(string $key): array;

    public function sismember(string $key, string $member): bool;

    /**
     * @return string[]
     */
    public function sinter(string $key, string $otherKey): array;

    public function srem(string $key, string $member): void;

    public function expire(string $key, int $ttl): void;

    public function set(string $key, int $value): void;

    public function get(string $key): ?string;

    public function del(string ...$keys): void;
}
