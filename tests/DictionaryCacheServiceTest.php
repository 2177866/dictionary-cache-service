<?php

namespace Alyakin\DictionaryCache\Tests;

use Alyakin\DictionaryCache\Contracts\RedisClientInterface;
use Alyakin\DictionaryCache\Services\DictionaryCacheService;
use Mockery;
use PHPUnit\Framework\TestCase;

class DictionaryCacheServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
    }

    public function test_constructor_requires_redis_client_when_auto_detection_unavailable(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Redis client is required');

        new DictionaryCacheService;
    }

    public function test_get_ttl_requires_context(): void
    {
        $service = new DictionaryCacheService(null, null, Mockery::mock(RedisClientInterface::class));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Undefined cacheKey');

        $service->getTTL();
    }

    public function test_set_ttl_updates_metadata_and_expires_existing_key(): void
    {
        $redis = Mockery::mock(RedisClientInterface::class);
        $service = new DictionaryCacheService(null, null, $redis);
        $service->setContext('user1');

        $redis->shouldReceive('set')
            ->once()
            ->with('dictionary_user1_ttl', 600);
        $redis->shouldReceive('exists')
            ->once()
            ->with('dictionary_user1')
            ->andReturn(true);
        $redis->shouldReceive('expire')
            ->once()
            ->with('dictionary_user1', 600);

        $service->setTTL(600);
        $this->assertTrue(true);
    }

    public function test_add_items_works_without_data_provider(): void
    {
        $redis = Mockery::mock(RedisClientInterface::class);
        $service = new DictionaryCacheService(null, null, $redis);
        $service->setContext('user1');

        $redis->shouldReceive('exists')
            ->once()
            ->with('dictionary_user1')
            ->andReturn(false);

        $redis->shouldReceive('sadd')
            ->once()
            ->with('dictionary_user1', 'one', 'two');

        $service->addItems(['one', 'two']);
        $this->assertTrue(true);
    }

    public function test_has_items_creates_temporary_set_and_cleans_it_up(): void
    {
        $redis = Mockery::mock(RedisClientInterface::class);
        $service = new DictionaryCacheService(null, null, $redis);
        $service->setContext('user1');

        $redis->shouldReceive('exists')
            ->twice()
            ->with('dictionary_user1')
            ->andReturn(true, true);

        $capturedTempKey = null;

        $redis->shouldReceive('sadd')
            ->once()
            ->withArgs(function ($key, ...$values) use (&$capturedTempKey) {
                $capturedTempKey = $key;

                return strpos($key, 'tmp_dictionary_user1_') === 0
                    && $values === ['foo', 'bar'];
            });

        $redis->shouldReceive('expire')
            ->once()
            ->withArgs(function ($key, $ttl) use (&$capturedTempKey) {
                return $key === $capturedTempKey && $ttl === 5;
            });

        $redis->shouldReceive('sinter')
            ->once()
            ->with('dictionary_user1', Mockery::on(function ($key) use (&$capturedTempKey) {
                return $key === $capturedTempKey;
            }))
            ->andReturn(['bar']);

        $redis->shouldReceive('del')
            ->once()
            ->with(Mockery::on(function ($key) use (&$capturedTempKey) {
                return $key === $capturedTempKey;
            }));

        $result = $service->hasItems(['foo', 'bar']);

        $this->assertSame(['bar'], $result);
    }

    public function test_has_items_deletes_temporary_set_when_sinter_fails(): void
    {
        $redis = Mockery::mock(RedisClientInterface::class);
        $service = new DictionaryCacheService(null, null, $redis);
        $service->setContext('user1');

        $redis->shouldReceive('exists')
            ->twice()
            ->with('dictionary_user1')
            ->andReturn(true, true);

        $capturedTempKey = null;

        $redis->shouldReceive('sadd')
            ->once()
            ->withArgs(function ($key, ...$values) use (&$capturedTempKey) {
                $capturedTempKey = $key;

                return strpos($key, 'tmp_dictionary_user1_') === 0
                    && $values === ['foo'];
            });

        $redis->shouldReceive('expire')
            ->once()
            ->withArgs(function ($key, $ttl) use (&$capturedTempKey) {
                return $key === $capturedTempKey && $ttl === 5;
            });

        $redis->shouldReceive('sinter')
            ->once()
            ->with('dictionary_user1', Mockery::on(function ($key) use (&$capturedTempKey) {
                return $key === $capturedTempKey;
            }))
            ->andThrow(new \RuntimeException('boom'));

        $redis->shouldReceive('del')
            ->once()
            ->with(Mockery::on(function ($key) use (&$capturedTempKey) {
                return $key === $capturedTempKey;
            }));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('boom');

        $service->hasItems(['foo']);
    }
}
